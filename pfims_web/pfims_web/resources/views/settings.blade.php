{{-- Prepare the values used by this page section. --}}
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
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/modal-policy.js') }}?v={{ filemtime(public_path('js/modal-policy.js')) }}"></script>
    {{-- Set the styles used by this page section. --}}
    <style id="pfims-session-privacy">html { visibility:hidden; }</style>
    {{-- Set up the actions used by this page section. --}}
    <script data-navigation-granted="{{ session('_page_navigation_granted', false) ? '1' : '0' }}" data-session-epoch="{{ session('navigation_epoch', '') }}" src="{{ asset('js/protected-navigation.js') }}?v={{ filemtime(public_path('js/protected-navigation.js')) }}"></script>
</head>
<body class="settings-page" data-portal="{{ $portal }}" data-current-user-id="{{ auth()->id() }}" data-is-admin="{{ $isAdmin ? '1' : '0' }}" data-config-api-base="{{ request()->getBaseUrl() }}/api/config">

    <!-- Success notification -->
    <div id="successNotification" class="success-notification" style="display: none; z-index: 4000;">
        <div class="success-content">
            <span class="success-icon">●</span>
            <span id="successMessage">Saved successfully!</span>
            <button class="success-close" onclick="closeSuccess()">×</button>
        </div>
    </div>

    <!-- Full-width header (fixed) -->
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

    <!-- Sidebar -->
    <aside class="sidebar">
                {{-- Show the page navigation. --}}
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
                        <img src="{{ asset('images/settings.png') }}" alt="Settings" class="nav-icon">
                        Settings
                    </a>
                </li>
                <li class="logout">
                    {{-- Collect the details entered in this form. --}}
                    <form method="POST" action="{{ url('/logout') }}" style="width: 100%; margin: 0; padding: 0;">
                        @csrf
                        <button type="submit" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%; background: none; border: none; cursor: pointer; padding: 0; font: inherit; color: inherit;">
                            <img src="{{ asset('images/logout.png') }}" alt="Log Out" class="nav-icon">
                            Log out
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </aside>

    <!-- Main content -->
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
                    {{-- Show this section when its display check passes. --}}
                    @if($isAdmin)
                        <li onclick="switchSettings(this, 'configurations')">Configurations</li>
                    @endif
                    <li onclick="switchSettings(this, 'trash')">Trash Bin</li>
                    <li onclick="switchSettings(this, 'defaultfilters')">Default Filters</li>
                    <li onclick="switchSettings(this, 'notifications')">Notifications</li>
                    {{-- Show this section when its display check passes. --}}
                    @if($isAdmin)
                        <li onclick="switchSettings(this, 'usermanagement')">User Management</li>
                    @endif
                </ul>
            </div>

            <!-- Settings Content -->
            <div class="settings-content">
                {{-- Show the section trash section. --}}
                <div id="section-trash" class="settings-section" style="display:none;">
                    <div class="section-title">Trash Bin</div>
                    <p>Deleted records are kept for up to 30 days. Restore them before expiry, or empty the Trash Bin to delete them permanently. Related records restore together without overwriting existing data.</p>
                    <div class="trash-actions" aria-label="Trash Bin actions">
                        <button type="button" id="restoreAllTrashBtn" class="btn-save pfims-expand-action" data-icon-label="restore" data-pfims-no-expand onclick="openTrashBulkModal('restore-all', this)" aria-label="Restore All" title="Restore All" disabled>
                            <span class="button-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 10 5 6l4-4M5 6h8a7 7 0 0 1 7 7v7M4 14v7h11M8 13v4h6"/></svg></span><span class="button-label">Restore All</span>
                        </button>
                        <button type="button" id="emptyTrashBtn" class="btn-delete pfims-expand-action" data-icon-label="empty-trash" data-pfims-no-expand onclick="openTrashBulkModal('empty', this)" aria-label="Empty Trash Bin" title="Empty Trash Bin" disabled>
                            <span class="button-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/></svg></span><span class="button-label">Empty Trash Bin</span>
                        </button>
                    </div>
                    <div class="table-wrapper">
                        {{-- Show the records in a table. --}}
                        <table id="trashTable" class="user-table" data-pfims-standard-actions="off">
                            <thead><tr><th scope="col">Record</th><th scope="col">Deleted</th><th scope="col">Permanent deletion after</th><th scope="col">Action</th></tr></thead>
                            <tbody id="trashTableBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Profile -->
                <div id="section-profile" class="settings-section">
                    <div class="section-title">Profile</div>
                    <div class="section-desc">Manage your personal information and account settings.</div>

                    <div class="profile-preview">
                        <div class="avatar">{{ $currentUserInitials }}</div>
                        <div class="info">
                            <div class="name">{{ $currentUserName }}</div>
                            <div class="role">{{ $currentUserRoleLabel }}</div>
                        </div>
                        <button class="btn-go-profile" data-url="{{ url('/profile') }}" onclick="window.pfimsNavigate(this.dataset.url)">Go to Profile</button>
                    </div>
                </div>

                <!-- Account & security -->
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

                <!-- System preferences -->
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

                <!-- Dropdown options -->
                @if($isAdmin)
                {{-- Show the section configurations section. --}}
                <div id="section-configurations" class="settings-section" style="display: none;">
                    <div class="section-title">Configurations</div>
                    <div class="section-desc">Manage operational thresholds and live form values. Project phase stages determine completion automatically.</div>

                    <div class="config-tabs">
                        <button class="config-tab active" onclick="switchConfigType(this, 'units')">Units</button>
                        <button class="config-tab" onclick="switchConfigType(this, 'inv_categories')">Inventory Categories</button>
                        <button class="config-tab" onclick="switchConfigType(this, 'exp_categories')">Expense Categories</button>
                        <button class="config-tab" onclick="switchConfigType(this, 'project_phases')">Project Phases</button>
                        <button class="config-tab" onclick="switchConfigType(this, 'project_managers')">Project Managers</button>
                        <button class="config-tab" onclick="switchConfigType(this, 'thresholds')">Thresholds</button>
                    </div>

                    {{-- Show the config crud panel section. --}}
                    <div id="configCrudPanel">
                        <div class="config-primary-actions" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                            <button class="btn-add-user" onclick="openConfigAddModal()">+ Add New</button>
                            {{-- Show the config table search section. --}}
                            <div class="pfims-mobile-search-panel"><input type="search" id="configTableSearch" oninput="renderConfigTable()" placeholder="Search configurations..." style="width:100%; max-width:320px; min-width:0; padding:9px 13px;border:1px solid #ddd;border-radius:8px;"></div>
                        </div>

                        <div class="table-wrapper" style="overflow-x: auto; margin-top: 15px;">
                            {{-- Show the records in a table. --}}
                            <table class="user-table" id="configTable" data-column-chooser="off">
                                <thead><tr><th>Name</th><th id="configExpenseTypeHeader" style="display:none;">Expense Type</th><th id="configStageHeader" style="display:none;">Stage</th><th style="text-align: center;">Action</th></tr></thead>
                                <tbody id="configTableBody"></tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Show the thresholds panel section. --}}
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
                        {{-- Show the save system settings section. --}}
                        <div style="display:flex;justify-content:flex-end;margin-top:18px;"><button type="button" class="btn-save" id="saveSystemSettings" onclick="saveSystemSettings(this)" style="background:#c9a96e;color:#fff;border:0;padding:10px 20px;border-radius:8px;font-weight:600;cursor:pointer;">Save thresholds</button></div>
                    </div>
                </div>
                @endif

                {{-- Show the section defaultfilters section. --}}
                <div id="section-defaultfilters" class="settings-section" style="display: none;">
                    <div class="section-title">Default Filters</div>
                    <div class="section-desc">Choose the filters that should be applied automatically whenever you open a module. You can temporarily change them on each page without changing these saved preferences.</div>
                    <div class="default-filter-config">
                        <label class="default-filter-module-field"><span>Module</span>
                            <select id="defaultFilterModule" onchange="renderDefaultFilterFields()">
                                <option value="dashboard">Dashboard</option>
                                <option value="projects">Projects</option>
                                <option value="finance.expenses">Finance — Expenses</option>
                                <option value="finance.budgets">Finance — Budgets &amp; Contracts</option>
                                <option value="finance.bonds">Finance — Construction Bonds</option>
                                <option value="inventory.items">Inventory — Items</option>
                                <option value="inventory.transactions">Inventory — Transactions</option>
                                <option value="suppliers">Suppliers</option>
                                <option value="reports">Reports</option>
                                <option value="analytics.material">Analytics — Material Projection</option>
                                <option value="analytics.budget">Analytics — Budget Comparison</option>
                            </select>
                        </label>
                        {{-- Show the default filter fields section. --}}
                        <div id="defaultFilterFields" class="default-filter-fields"></div>
                        <div class="default-filter-actions">
                            <button type="button" class="btn-cancel" onclick="removeDefaultFilters()">Remove saved defaults</button>
                            <button type="button" class="btn-save" onclick="saveDefaultFilters()">Save defaults</button>
                        </div>
                        <p id="defaultFilterStatus" class="default-filter-status" role="status" aria-live="polite"></p>
                    </div>
                </div>

                <!-- Notifications -->
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

                <!-- User management -->
                @if($isAdmin)
                {{-- Show the section usermanagement section. --}}
                <div id="section-usermanagement" class="settings-section" style="display: none;">
                    <div class="section-title">User Management</div>
                    <div class="section-desc">Manage all user accounts, their roles, and permissions.</div>

                    <button class="btn-add-user" onclick="openAddUserModal()">+ Add User</button>

                    <div class="table-wrapper" style="overflow-x: auto; margin-top: 15px;">
                        {{-- Show the records in a table. --}}
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
                                    <tbody id="userTableBody">
                                        {{-- Show each u. --}}
                                        @foreach($users as $u)
                                        <tr>
                                            <td><strong>{{ $u->name }}</strong></td>
                                            <td>{{ $u->email }}</td>
                                            <td>
                                                {{-- Prepare the values used by this page section. --}}
                                                @php
                                                    $roleLabel = ucfirst($u->role ?? 'user');
                                                @endphp
                                                <span class="role-badge {{ $u->role === 'admin' ? 'admin' : ($u->role === 'accounting' ? 'manager' : 'staff') }}">{{ $roleLabel }}</span>
                                            </td>
                                            <td>{{ $u->status ?? 'Active' }}</td>
                                            <td class="settings-actions-cell">
                                                <div class="settings-action-group">
                                                    <button type="button" class="btn-config-action btn-view-user" data-user-id="{{ $u->id }}" aria-label="View {{ $u->name }}" onclick="openUserDetails(Number(this.dataset.userId))">View</button>
                                                    <button type="button" class="btn-config-action btn-edit-config" data-user-id="{{ $u->id }}" aria-label="Edit {{ $u->name }}" onclick="openUserConfig(Number(this.dataset.userId))">Edit</button>
                                                    <button type="button" class="btn-config-action btn-delete-config" data-user-id="{{ $u->id }}" aria-label="Delete {{ $u->name }}" onclick="deleteUserById(Number(this.dataset.userId))">Delete</button>
                                                </div>
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

    <!-- User details modal -->
    <div id="userDetailsModal" class="modal-overlay settings-modal-overlay settings-user-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="userDetailsModalTitle">
        <div class="modal-container settings-modal-dialog">
            <div class="modal-header">
                <h2 id="userDetailsModalTitle">User details</h2>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeUserDetails()">×</button>
            </div>
            {{-- Show the user details fields section. --}}
            <div id="userDetailsFields" class="modal-body"></div>
            <div class="modal-footer settings-modal-footer">
                <button type="button" class="btn-cancel" onclick="closeUserDetails()">Cancel</button>
                <button type="button" class="btn-save" onclick="editUserFromDetails()">Edit</button>
                <button type="button" class="btn-delete-config" onclick="deleteUserFromDetails()">Delete User</button>
            </div>
        </div>
    </div>

    <!-- User edit modal -->
    <div id="userEditModal" class="modal-overlay settings-modal-overlay settings-user-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="userEditModalTitle">
        <div class="modal-container settings-modal-dialog">
            <div class="modal-header">
                <h2 id="userEditModalTitle">Edit user</h2>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeUserEdit()">×</button>
            </div>
            <div class="modal-body">
                {{-- Show the config name section. --}}
                <div class="form-group"><label for="configName">Full Name</label><input type="text" id="configName"></div>
                {{-- Show the config email section. --}}
                <div class="form-group"><label for="configEmail">Email</label><input type="email" id="configEmail"></div>
                <div class="settings-user-fields">
                    {{-- Show the config role section. --}}
                    <div class="form-group"><label for="configRole">Role</label><select id="configRole"><option value="admin">Admin</option><option value="accounting">Accounting</option><option value="operations">Operations</option></select></div>
                    {{-- Show the config user status section. --}}
                    <div class="form-group"><label for="configUserStatus">Status</label><select id="configUserStatus"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
                </div>
            </div>
            <div class="modal-footer settings-modal-footer">
                <button type="button" class="btn-cancel" onclick="closeUserEdit()">Cancel</button>
                <button type="button" class="btn-save" onclick="saveUserConfig(this)">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Config item modal (add/edit) -->
    <div id="deleteUserConfirmModal" class="modal-overlay pfims-delete-confirm" style="display:none; z-index:9999;" role="dialog" aria-modal="true" aria-labelledby="deleteUserModalTitle">
        <div class="modal-container">
            <div class="modal-header">
                <h2 id="deleteUserModalTitle">Confirm Deletion</h2>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeDeleteUserModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteUserConfirmMessage">Are you sure you want to move this user to Trash Bin?</p>
                <p>You can restore deleted records from Settings → Trash Bin for 30 days before permanent deletion.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeDeleteUserModal()">Cancel</button>
                <button type="button" id="confirmDeleteUserBtn" class="btn-delete" onclick="confirmDeleteUser()">Delete</button>
            </div>
        </div>
    </div>

    {{-- Show the config delete confirm modal section. --}}
    <div id="configDeleteConfirmModal" class="modal-overlay pfims-delete-confirm" style="display:none; z-index:9999;" role="dialog" aria-modal="true" aria-labelledby="configDeleteModalTitle">
        <div class="modal-container">
            <div class="modal-header">
                <h2 id="configDeleteModalTitle">Confirm Deletion</h2>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeConfigDeleteModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="configDeleteMessage">Are you sure you want to move this configuration to Trash Bin?</p>
                <p>You can restore deleted records from Settings → Trash Bin for 30 days before permanent deletion.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeConfigDeleteModal()">Cancel</button>
                <button type="button" id="confirmDeleteConfigBtn" class="btn-delete" onclick="confirmDeleteConfig()">Delete</button>
            </div>
        </div>
    </div>

    {{-- Show the restore trash confirm modal section. --}}
    <div id="restoreTrashConfirmModal" class="modal-overlay pfims-restore-confirm" style="display:none; z-index:9999;" role="dialog" aria-modal="true" aria-labelledby="restoreTrashModalTitle" aria-describedby="restoreTrashMessage restoreTrashNote">
        <div class="modal-container">
            <div class="modal-header">
                <h2 id="restoreTrashModalTitle">Confirm Restoration</h2>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeTrashRestoreModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="restoreTrashMessage">Are you sure you want to restore this record?</p>
                <p id="restoreTrashNote">Related records will be restored together. Existing records will not be overwritten.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeTrashRestoreModal()">Cancel</button>
                <button type="button" id="confirmTrashRestoreBtn" class="btn-save" onclick="confirmTrashAction()">Restore</button>
            </div>
        </div>
    </div>

    {{-- Show the empty trash confirm modal section. --}}
    <div id="emptyTrashConfirmModal" class="modal-overlay pfims-restore-confirm" style="display:none; z-index:9999;" role="dialog" aria-modal="true" aria-labelledby="emptyTrashModalTitle" aria-describedby="emptyTrashMessage emptyTrashNote">
        <div class="modal-container">
            <div class="modal-header">
                <h2 id="emptyTrashModalTitle">Empty Trash Bin</h2>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeEmptyTrashModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="emptyTrashMessage">Are you sure you want to permanently delete all records in your Trash Bin?</p>
                <p id="emptyTrashNote">This permanently deletes these records and cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEmptyTrashModal()">Cancel</button>
                <button type="button" id="confirmEmptyTrashBtn" class="btn-delete" onclick="confirmTrashAction()">Empty Trash Bin</button>
            </div>
        </div>
    </div>

    {{-- Show the config details modal section. --}}
    <div id="configDetailsModal" class="modal-overlay settings-modal-overlay settings-details-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="configDetailsModalTitle">
        <div class="modal-container settings-modal-dialog">
            <div class="modal-header">
                <h2 id="configDetailsModalTitle">Configuration details</h2>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeConfigDetailsModal()">×</button>
            </div>
            {{-- Show the config details fields section. --}}
            <div id="configDetailsFields" class="modal-body"></div>
            <div class="modal-footer settings-modal-footer">
                <button type="button" class="btn-cancel" onclick="closeConfigDetailsModal()">Cancel</button>
                <button type="button" class="btn-delete-config" onclick="deleteConfigFromDetails()">Delete</button>
                <button type="button" class="btn-save" onclick="editConfigFromDetails()">Edit</button>
            </div>
        </div>
    </div>

    {{-- Show the config item modal section. --}}
    <div id="configItemModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 3300; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
        <div class="modal-container" style="background: #fff; width: 500px; max-width: 95%; border-radius: 16px; padding: 30px 35px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 1.4rem; font-weight: 600; color: #1a2b3c; margin: 0;" id="configItemModalTitle">Add New Item</h2>
                <button class="modal-close" onclick="closeConfigItemModal()" style="background: none; border: none; font-size: 2rem; cursor: pointer; color: #888; line-height: 1;">×</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="configItemId">
                {{-- Show the config item fields section. --}}
                <div id="configItemFields"></div>
                <div style="margin-top: 20px; display: flex; justify-content: flex-end; align-items: center; gap: 12px; border-top: 1px solid #e9ecef; padding-top: 20px;">
                    <button class="btn-cancel" onclick="closeConfigItemModal()" style="background: transparent; color: #888; border: 1px solid #ddd; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;">Cancel</button>
                    <button class="btn-save" onclick="saveConfigItem(this)" style="background: #c9a96e; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s;">Save</button>
                </div>
            </div>
        </div>
    </div>

                    <!-- Password display modal -->
                    <div id="passwordModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 3000; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
                        <div class="modal-container" style="background: #fff; width: 480px; max-width: 95%; border-radius: 12px; padding: 22px 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.25);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                                <div>
                                    <h3 style="margin:0; font-size:1.1rem;">User Created</h3>
                                    <div style="font-size:0.9rem; color:#666;">The initial password is the part of the user’s email before @. Share it securely with the user.</div>
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

    <!-- Add user modal -->
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

    <!-- Change password modal -->
    <div id="changePasswordModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 3000; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
        <div class="modal-container" style="background: #fff; width: 480px; max-width: 95%; border-radius: 16px; padding: 30px 35px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 1.4rem; font-weight: 600; color: #1a2b3c; margin: 0;">Change Password</h2>
                <button class="modal-close" onclick="closeChangePasswordModal()" style="background: none; border: none; font-size: 2rem; cursor: pointer; color: #888; line-height: 1; transition: 0.2s;">×</button>
            </div>
            <div class="modal-body">
                {{-- Collect the details entered in this form. --}}
                <form id="changePasswordForm" onsubmit="submitChangePassword(event)">
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 500; color: #333; margin-bottom: 4px;">Current Password <span style="color: #d32f2f;">*</span></label>
                        <div class="password-control" style="position: relative;">
                            <input type="password" id="currentPassword" placeholder="Enter your current password" style="width: 100%; padding: 10px 44px 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; background: #fafafa; transition: 0.3s;" required>
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" onclick="togglePasswordVisibility('currentPassword', this)"></button>
                        </div>
                        {{-- Show the current password error section. --}}
                        <div id="currentPasswordError" style="color: #d32f2f; font-size: 0.8rem; margin-top: 4px; display: none;"></div>
                    </div>
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 500; color: #333; margin-bottom: 4px;">New Password <span style="color: #d32f2f;">*</span></label>
                        <div class="password-control" style="position: relative;">
                            <input type="password" id="newPassword" placeholder="Enter new password (min 8 characters)" style="width: 100%; padding: 10px 44px 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; background: #fafafa; transition: 0.3s;" required minlength="8">
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" onclick="togglePasswordVisibility('newPassword', this)"></button>
                        </div>
                        <div style="font-size: 0.75rem; color: #888; margin-top: 4px;">Password must be at least 8 characters long</div>
                        {{-- Show the new password error section. --}}
                        <div id="newPasswordError" style="color: #d32f2f; font-size: 0.8rem; margin-top: 4px; display: none;"></div>
                    </div>
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 500; color: #333; margin-bottom: 4px;">Confirm New Password <span style="color: #d32f2f;">*</span></label>
                        <div class="password-control" style="position: relative;">
                            <input type="password" id="confirmPassword" placeholder="Re-enter new password" style="width: 100%; padding: 10px 44px 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; background: #fafafa; transition: 0.3s;" required>
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" onclick="togglePasswordVisibility('confirmPassword', this)"></button>
                        </div>
                        {{-- Show the confirm password error section. --}}
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

    {{-- Set up the actions used by this page section. --}}
    <script>
                // Settings navigation
        var csrfToken = '{{ csrf_token() }}';

        // Button loading state
        function setButtonLoading(button, isLoading, loadingText) {
            // Return early if the button is missing or false.
            if (!button) return;
            // Run this step if the is loading is set.
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

        // Update signed in user.
        function updateSignedInUser(user) {
            // Return early if the user is missing or false or the string does not equal the current user id.
            if (!user || String(user.id)!==document.body.dataset.currentUserId) return false;
            // Run this step if the role does not equal admin.
            if (user.role !== 'admin') {
                window.pfimsNavigate(user.role === 'accounting' ? '/asettings' : '/osettings');
                return true;
            }
            document.querySelector('.top-header .right a[href*="profile"] span')?.replaceChildren(document.createTextNode(user.name));
            document.querySelector('.profile-preview .info .name').textContent=user.name;
            var parts=user.name.trim().split(/\s+/);
            document.querySelector('.profile-preview .avatar').textContent=(Array.from(parts[0] || 'U')[0]+(parts.length>1?Array.from(parts[parts.length-1])[0]:'')).toUpperCase();
            return false;
        }

        // Refresh users.
        async function refreshUsers() {
            // Return early if the is admin is missing or false.
            if (!isAdmin) return;
            // Try this action and handle any errors below.
            try {
                var response = await fetch('/users', {headers:{'Accept':'application/json'},cache:'no-store'});
                // Run this step if the response ok is missing or false.
                if (!response.ok) throw new Error('Users could not be updated. Please try again.');
                var result = await response.json();
                var body = document.getElementById('userTableBody');
                body.replaceChildren();
                // Process each user.
                result.data.forEach(function(user) {
                    var row = body.insertRow();
                    // Process each value.
                    [user.name, user.email, userRoleLabel(user.role), user.status].forEach(function(value) {
                        var cell=row.insertCell(); cell.textContent=value || '—';
                        // Run this step if the cells length equals 1.
                        if (row.cells.length===1) { var name=document.createElement('strong'); name.textContent=value; cell.replaceChildren(name); }
                        // Run this step if the cells length equals 3.
                        if (row.cells.length===3) { var badge=document.createElement('span'); badge.className='role-badge '+(user.role==='admin'?'admin':user.role==='accounting'?'manager':'staff'); badge.textContent=value; cell.replaceChildren(badge); }
                    });
                    var actions = row.insertCell(); actions.className='settings-actions-cell';
                    var group = document.createElement('div'); group.className='settings-action-group';
                    // Process each action.
                    [['View',openUserDetails,'btn-view-user'],['Edit',openUserConfig,'btn-edit-config'],['Delete',deleteUserById,'btn-delete-config']].forEach(function(action) {
                        var button=document.createElement('button'); button.type='button'; button.className='btn-config-action '+action[2];
                        button.textContent=action[0]; button.dataset.userId=user.id; button.setAttribute('aria-label',action[0]+' '+user.name);
                        button.onclick=function(){action[1](user.id);}; group.appendChild(button);
                    });
                    actions.appendChild(group);
                });
            } catch(error) { alert(error.message); }
        }

        // Get the redirect to user management.
        function redirectToUserManagement() {
            closeUserConfig();
            refreshUsers();
        }

        // Get the switch settings.
        function switchSettings(el, section) {
            var navItems = document.querySelectorAll('.settings-nav li');
            // Process each item.
            navItems.forEach(function(item) {
                item.classList.remove('active');
            });
            el.classList.add('active');

            var sections = document.querySelectorAll('.settings-section');
            // Process each sec.
            sections.forEach(function(sec) {
                sec.style.display = 'none';
            });

            var target = document.getElementById('section-' + section);
            // Run this step if the target is set.
            if (target) {
                target.style.display = 'block';
            }

            closeUserConfig();
            closeUserDetails();
            closeConfigItemModal();
            closeConfigDetailsModal();
            closeConfigDeleteModal();
            closeAddUserModal();

            // Run this step if the section equals trash.
            if (section === 'trash') loadTrash();
            console.log('Switched to: ' + section);
        }

        // Toggle switch
        function toggleSwitch(el) {
            el.classList.toggle('active');
            var status = el.classList.contains('active') ? 'Enabled' : 'Disabled';
            console.log('Switch toggled: ' + status);
        }

        // Switch notif category.
        function toggleNotifCategory(el) {
            var category = el.getAttribute('data-notif-category');
            el.classList.toggle('active');
            localStorage.setItem('notif_mute_' + category, el.classList.contains('active') ? 'false' : 'true');
        }

        // Set up notif category toggles.
        function initNotifCategoryToggles() {
            // Process each el.
            document.querySelectorAll('[data-notif-category]').forEach(function(el) {
                var muted = localStorage.getItem('notif_mute_' + el.getAttribute('data-notif-category')) === 'true';
                el.classList.toggle('active', !muted);
            });
        }

        document.addEventListener('DOMContentLoaded', initNotifCategoryToggles);

        // User config (crud)
        var csrfToken = '{{ csrf_token() }}';
        var activeConfigUserId = null;

        // Load user config.
        function fetchUserConfig(id) {
            activeConfigUserId = id;
            // Use the result when this request finishes.
            return fetch('/users/' + id, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(function(res) {
                // Run this step if the res ok is missing or false.
                if (!res.ok) throw new Error('Unable to load user');
                return res.json();
            });
        }

        // Get the populate user config.
        function populateUserConfig(user) {
            document.getElementById('configRole').value = user.role || 'operations';
            document.getElementById('configName').value = user.name || '';
            document.getElementById('configEmail').value = user.email || '';
            document.getElementById('configUserStatus').value = user.status || 'Active';
        }

        // Get the user role label.
        function userRoleLabel(role) {
            return role ? role.charAt(0).toUpperCase() + role.slice(1) : 'User';
        }

        // Open user config.
        function openUserConfig(id) {
            closeUserDetails(false);
            // Use the result when this request finishes.
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

        // Open user details.
        function openUserDetails(id) {
            closeUserEdit(false);
            // Use the result when this request finishes.
            fetchUserConfig(id).then(function(user) {
                var fields = document.getElementById('userDetailsFields');
                fields.innerHTML = '';
                // Process each entry.
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

        // Close user details.
        function closeUserDetails(clearActive) {
            document.getElementById('userDetailsModal').style.display = 'none';
            // Run this step if the clear active does not equal false.
            if (clearActive !== false) activeConfigUserId = null;
            restoreSettingsBodyOverflow();
        }

        // Close user edit.
        function closeUserEdit() {
            document.getElementById('userEditModal').style.display = 'none';
            activeConfigUserId = null;
            restoreSettingsBodyOverflow();
        }

        // Get the edit user from details.
        function editUserFromDetails() {
            var id = activeConfigUserId;
            closeUserDetails(false);
            // Run this step if the id is set.
            if (id) openUserConfig(id);
        }

        // Delete user from details.
        function deleteUserFromDetails() {
            // Return early if the active config user id is missing or false.
            if (!activeConfigUserId) return;
            closeUserDetails(false);
            deleteUserFromConfig();
        }

        // Close user config.
        function closeUserConfig() {
            closeUserEdit();
        }

                // Save user config.
                function saveUserConfig(btn) {
            // Return early if the active config user id is missing or false.
            if (!activeConfigUserId) return;
            var payload = {
                name: document.getElementById('configName').value.trim(),
                email: document.getElementById('configEmail').value.trim(),
                role: document.getElementById('configRole').value,
                status: document.getElementById('configUserStatus').value,
                _token: csrfToken
            };

            // Run this step if the name is missing or false or the email is missing or false.
            if (!payload.name || !payload.email) {
                alert('Name and email are required.');
                return;
            }

            setButtonLoading(btn, true, 'Saving...');

            // Use the result when this request finishes.
            fetch('/users/' + activeConfigUserId, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json().then(function(data) { return { ok: res.ok, data: data }; }); })
            .then(function(data) {
                // Run this step if the data ok is set and the success is set.
                if (data.ok && data.data.success) {
                    showSuccess('User updated successfully!');
                    // Run this step if the update signed in user is missing or false.
                    if (!updateSignedInUser(data.data.user)) redirectToUserManagement();
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

        // Delete user from config.
        function deleteUserFromConfig() {
            // Return early if the active config user id is missing or false.
            if (!activeConfigUserId) return;
            document.getElementById('deleteUserConfirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Delete user by id.
        function deleteUserById(id) {
            // Return early if the id is missing or false.
            if (!id) return;
            activeConfigUserId = id;
            deleteUserFromConfig();
        }

        // Close delete user modal.
        function closeDeleteUserModal() {
            document.getElementById('deleteUserConfirmModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        // Get the confirm delete user.
        function confirmDeleteUser() {
            // Return early if the active config user id is missing or false.
            if (!activeConfigUserId) return;
            var confirmButton = document.getElementById('confirmDeleteUserBtn');
            confirmButton.disabled = true;
            confirmButton.textContent = 'Deleting...';

            // Use the result when this request finishes.
            fetch('/users/' + activeConfigUserId, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                // Run this step if the success is set.
                if (data.success) {
                    closeDeleteUserModal();
                    showSuccess('User deleted');
                    redirectToUserManagement();
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
                confirmButton.textContent = 'Delete';
            });
        }

        // Handle the click event.
        document.getElementById('deleteUserConfirmModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) closeDeleteUserModal();
        });

        var trashEntries = [];
        var trashLoading = false;
        var trashLoadVersion = 0;
        var pendingTrashAction = null;
        var trashActionInProgress = false;

        // Update trash action buttons.
        function updateTrashActionButtons() {
            // Process each id.
            ['restoreAllTrashBtn', 'emptyTrashBtn'].forEach(function(id) {
                document.getElementById(id).disabled = trashLoading || trashActionInProgress || !trashEntries.length;
            });
        }

        // Load trash.
        async function loadTrash() {
            var body = document.getElementById('trashTableBody');
            var version = ++trashLoadVersion;
            trashLoading = true;
            updateTrashActionButtons();
            // Try this action and handle any errors below.
            try {
                var response = await fetch('/api/trash', {headers:{'Accept':'application/json'}});
                var result = await response.json();
                // Return early if the version does not equal the trash load version.
                if (version !== trashLoadVersion) return;
                // Run this step if the response ok is missing or false.
                if (!response.ok) throw new Error(result.message || 'Unable to load Trash Bin.');
                trashEntries = result.data;
                var rows = document.createDocumentFragment();
                // Process each entry.
                trashEntries.forEach(function(entry) {
                    var row = document.createElement('tr');
                    // Process each value.
                    [entry.label, entry.deleted_at, entry.expires_at].forEach(function(value) {
                        var cell = document.createElement('td'); cell.textContent = value; row.appendChild(cell);
                    });
                    var cell = document.createElement('td');
                    var button = document.createElement('button');
                    button.type = 'button'; button.className = 'btn-config-action'; button.textContent = 'Restore';
                    button.onclick = function() { openTrashRestoreModal(entry, button); };
                    cell.appendChild(button); row.appendChild(cell); rows.appendChild(row);
                });
                // Run this step if the trash entries length is missing or false.
                if (!trashEntries.length) {
                    var row = document.createElement('tr'); var cell = document.createElement('td');
                    cell.colSpan = 4; cell.textContent = 'Trash Bin is empty.';
                    row.appendChild(cell); rows.appendChild(row);
                }
                body.replaceChildren(rows);
            } catch(error) {
                // Run this step if the version equals the trash load version.
                if (version === trashLoadVersion) alert(error.message);
            } finally {
                // Run this step if the version equals the trash load version.
                if (version === trashLoadVersion) { trashLoading = false; updateTrashActionButtons(); }
            }
        }

        // Open trash restore modal.
        function openTrashRestoreModal(entry, button) { openTrashAction('restore', button, entry); }
        // Open trash bulk modal.
        function openTrashBulkModal(type, button) { openTrashAction(type, button); }

        // Open trash action.
        function openTrashAction(type, button, entry) {
            // Return early if the value checks match.
            if (trashActionInProgress || trashLoading || (type !== 'restore' && !trashEntries.length)) return;
            // Return early if the includes is missing or false.
            if (!['restore', 'restore-all', 'empty'].includes(type)) return;
            // Build a value for each item.
            var ids = entry ? [entry.id] : trashEntries.map(function(item) { return item.id; });
            var empty = type === 'empty';
            var modalId = empty ? 'emptyTrashConfirmModal' : 'restoreTrashConfirmModal';
            pendingTrashAction = {type:type, ids:ids, button:button, modalId:modalId};
            var countLabel = ids.length + (ids.length === 1 ? ' record' : ' records');
            // Run this step if the empty is set.
            if (empty) {
                document.getElementById('emptyTrashMessage').textContent = 'Are you sure you want to permanently delete all ' + countLabel + ' in your Trash Bin?';
            } else {
                var all = type === 'restore-all';
                document.getElementById('restoreTrashModalTitle').textContent = all ? 'Confirm Restore All' : 'Confirm Restoration';
                document.getElementById('restoreTrashMessage').textContent = all
                    ? 'Are you sure you want to restore all ' + countLabel + ' in your Trash Bin?'
                    : 'Are you sure you want to restore “' + entry.label + '”?';
                document.getElementById('restoreTrashNote').textContent = all
                    ? 'Related records will be restored together. If any record cannot be restored, nothing will be changed.'
                    : 'Related records will be restored together. Existing records will not be overwritten.';
                document.getElementById('confirmTrashRestoreBtn').textContent = all ? 'Restore All' : 'Restore';
            }
            document.getElementById(modalId).style.display = 'flex';
            document.body.style.overflow = 'hidden';
            document.querySelector('#' + modalId + ' .btn-cancel').focus();
        }

        // Close trash restore modal.
        function closeTrashRestoreModal() { closeTrashActionModal('restoreTrashConfirmModal'); }
        // Close empty trash modal.
        function closeEmptyTrashModal() { closeTrashActionModal('emptyTrashConfirmModal'); }

        // Close trash action modal.
        function closeTrashActionModal(modalId) {
            // Return early if the trash action in progress is set.
            if (trashActionInProgress) return;
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = '';
            // Run this step if the pending trash action is set and the is connected is set.
            if (pendingTrashAction && pendingTrashAction.button.isConnected) pendingTrashAction.button.focus();
            pendingTrashAction = null;
        }

        // Get the confirm trash action.
        async function confirmTrashAction() {
            // Return early if the pending trash action is missing or false or the trash action in progress is set.
            if (!pendingTrashAction || trashActionInProgress) return;
            var selection = pendingTrashAction;
            var empty = selection.type === 'empty';
            var single = selection.type === 'restore';
            var confirmButton = document.getElementById(empty ? 'confirmEmptyTrashBtn' : 'confirmTrashRestoreBtn');
            var originalLabel = confirmButton.textContent;
            var controls = document.querySelectorAll('#' + selection.modalId + ' button');
            trashActionInProgress = true;
            updateTrashActionButtons();
            // Process each button.
            controls.forEach(function(button) { button.disabled = true; });
            confirmButton.textContent = empty ? 'Emptying...' : 'Restoring...';
            var succeeded = false;
            var outdated = false;
            var message = '';
            // Try this action and handle any errors below.
            try {
                var url = single ? '/api/trash/' + selection.ids[0] + '/restore' : empty ? '/api/trash' : '/api/trash/restore-all';
                var response = await fetch(url, {
                    method: empty ? 'DELETE' : 'POST',
                    headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                    body: JSON.stringify({confirmed:true, ids:selection.ids})
                });
                var result = await response.json();
                // Run this step if the response ok is missing or false.
                if (!response.ok) {
                    outdated = response.status === 404 || response.status === 410;
                    throw new Error(result.message || 'Unable to complete the Trash Bin action.');
                }
                succeeded = true; message = result.message;
            } catch(error) {
                alert(error.message);
            } finally {
                trashActionInProgress = false;
                // Process each button.
                controls.forEach(function(button) { button.disabled = false; });
                confirmButton.textContent = originalLabel;
                updateTrashActionButtons();
            }
            // Run this step if the succeeded is set.
            if (succeeded) {
                closeTrashActionModal(selection.modalId);
                showSuccess(message);
                loadTrash();
                // Run this step if the empty is missing or false.
                if (!empty) {
                    refreshUsers();
                    // Run this step if the is admin is set and the current config type does not equal thresholds.
                    if (isAdmin && currentConfigType !== 'thresholds') fetchConfigItems(currentConfigType);
                    // Run this step if the is admin is set.
                    if (isAdmin) loadSystemSettings();
                    loadDefaultFilters();
                }
            } else if (outdated) {
                closeTrashActionModal(selection.modalId);
                loadTrash();
            }
        }

        // Refresh visible trash.
        function refreshVisibleTrash() {
            var section = document.getElementById('section-trash');
            // Run this step if the value checks match.
            if (!document.hidden && section.style.display !== 'none' && !trashActionInProgress && !pendingTrashAction) loadTrash();
        }
        window.addEventListener('focus', refreshVisibleTrash);
        document.addEventListener('visibilitychange', refreshVisibleTrash);


        // Dropdown options
        var configData = {
            'units': [],
            'inv_categories': [],
            'exp_categories': []
            , 'project_phases': []
            , 'project_managers': []
        };

        var configFieldMap = {
            'units': { id: 'unit_id', name: 'unit_name' },
            'inv_categories': { id: 'inventory_category_id', name: 'inventory_category_name' },
            'exp_categories': { id: 'fin_category_id', name: 'category_name' }
            , 'project_phases': { id: 'phase_id', name: 'phase_name' }
            , 'project_managers': { id: 'manager_id', name: 'manager_name' }
        };
        var configMeta = {};

        var currentConfigType = 'units';
        var configApiBase = document.body.dataset.configApiBase;
        var isAdmin = document.body.dataset.isAdmin === '1';

        // Get the switch config type.
        function switchConfigType(el, type) {
            // Return early if the is admin is missing or false.
            if (!isAdmin) return;
            var btns = document.querySelectorAll('.config-tab');
            // Process each btn.
            btns.forEach(function(btn) {
                btn.classList.remove('active');
            });
            el.classList.add('active');
            currentConfigType = type;
            var isThresholds = type === 'thresholds';
            document.getElementById('configCrudPanel').style.display = isThresholds ? 'none' : 'block';
            document.getElementById('thresholdsPanel').style.display = isThresholds ? 'block' : 'none';
            // Run this step if the is thresholds is set.
            if (isThresholds) {
                loadSystemSettings();
                return;
            }
            document.getElementById('configTableSearch').value = '';
            fetchConfigItems(type);
        }

        // Load config items.
        function fetchConfigItems(type) {
            // Return early if the is admin is missing or false.
            if (!isAdmin) return;
            var tbody = document.getElementById('configTableBody');
            // Return early if the tbody is missing or false.
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="2" style="text-align:center; padding: 16px;">Loading...</td></tr>';

            // Use the result when this request finishes.
            fetch(configApiBase + '/' + type, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                // Run this step if the success is set.
                if (data.success) {
                    configData[type] = data.data || [];
                    configMeta[type] = data.meta || null;
                    renderConfigTable();
                } else {
                    tbody.innerHTML = '<tr><td colspan="2" style="text-align:center; padding: 16px; color: red;">Failed to load data.</td></tr>';
                }
            })
            .catch(function(err) {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="2" style="text-align:center; padding: 16px; color: red;">Failed to load data.</td></tr>';
            });
        }

        // Show config table.
        function renderConfigTable() {
            // Return early if the is admin is missing or false.
            if (!isAdmin) return;
            var items = configData[currentConfigType] || [];
            var search = document.getElementById('configTableSearch').value.trim().toLowerCase();
            var tbody = document.getElementById('configTableBody');
            // Return early if the tbody is missing or false.
            if (!tbody) return;
            tbody.innerHTML = '';
            var fields = configFieldMap[currentConfigType];
            var showsStage = currentConfigType === 'project_phases';
            var showsExpenseType = currentConfigType === 'exp_categories';
            document.getElementById('configStageHeader').style.display = showsStage ? '' : 'none';
            document.getElementById('configExpenseTypeHeader').style.display = showsExpenseType ? '' : 'none';
            // Run this step if the search is set.
            if (search) {
                // Keep the matching item items.
                items = items.filter(function(item) {
                    return String(item[fields.name] || '').toLowerCase().indexOf(search) !== -1;
                });
            }

            // Run this step if the items length is missing or false.
            if (!items.length) {
                tbody.innerHTML = '<tr><td colspan="' + (showsStage || showsExpenseType ? '3' : '2') + '" style="text-align:center; padding: 16px;">No items found.</td></tr>';
                return;
            }

            // Process each item.
            items.forEach(function(item) {
                var tr = document.createElement('tr');
                var nameCell = document.createElement('td');
                var strong = document.createElement('strong');
                strong.textContent = item[fields.name];
                nameCell.appendChild(strong);
                var stageCell = null;
                // Run this step if the shows stage is set.
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

                tr.appendChild(nameCell);
                // Run this step if the shows expense type is set.
                if (showsExpenseType) {
                    var typeCell = document.createElement('td');
                    typeCell.textContent = configValueLabel(configMeta.exp_categories.fields.classification, item.classification);
                    tr.appendChild(typeCell);
                }
                // Run this step if the stage cell is set.
                if (stageCell) tr.appendChild(stageCell);
                tr.appendChild(actionCell);
                tbody.appendChild(tr);
            });
        }

        // Open config add modal.
        function openConfigAddModal() {
            document.getElementById('configItemId').value = '';
            renderConfigFields(null);
            var stageInput = document.getElementById('configField_stage_order');
            // Run this step if the stage input is set.
            if (stageInput) stageInput.value = String((configData.project_phases || []).length + 1);
            document.getElementById('configItemModalTitle').textContent = 'Add Configuration';
            document.getElementById('configItemModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Open config edit modal.
        function openConfigEditModal(id) {
            var item = findConfigItem(id);
            // Return early if the item is missing or false.
            if (!item) return;
            document.getElementById('configItemId').value = id;
            renderConfigFields(item);
            document.getElementById('configItemModalTitle').textContent = 'Edit Configuration';
            document.getElementById('configItemModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Close config item modal.
        function closeConfigItemModal() {
            document.getElementById('configItemModal').style.display = 'none';
            restoreSettingsBodyOverflow();
        }

        var activeConfigItemId = null;

        // Get the find config item.
        function findConfigItem(id) {
            var fields = configFieldMap[currentConfigType];
            // Run the find action.
            return (configData[currentConfigType] || []).find(function(item) {
                return String(item[fields.id]) === String(id);
            });
        }

        // Get the config value label.
        function configValueLabel(definition, value) {
            return definition && definition.options && definition.options[String(value)] !== undefined
                ? definition.options[String(value)] : String(value === null || value === undefined ? '' : value);
        }

        // Open config details modal.
        function openConfigDetailsModal(id) {
            var item = findConfigItem(id);
            var meta = configMeta[currentConfigType];
            // Return early if the item is missing or false or the meta is missing or false.
            if (!item || !meta) return;
            activeConfigItemId = id;
            document.getElementById('configDetailsModalTitle').textContent = 'View ' + (item[meta.name] || 'configuration');
            var container = document.getElementById('configDetailsFields');
            container.innerHTML = '';
            // Process each field.
            Object.keys(meta.fields || {}).forEach(function(field) {
                var definition = meta.fields[field];
                var row = document.createElement('div');
                row.className = 'settings-detail-row';
                var label = document.createElement('strong');
                label.textContent = definition.label;
                var value = document.createElement('span');
                value.textContent = configValueLabel(definition, item[field]);
                row.append(label, value);
                container.appendChild(row);
            });
            document.getElementById('configDetailsModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Close config details modal.
        function closeConfigDetailsModal() {
            document.getElementById('configDetailsModal').style.display = 'none';
            activeConfigItemId = null;
            restoreSettingsBodyOverflow();
        }

        // Get the edit config from details.
        function editConfigFromDetails() {
            var id = activeConfigItemId;
            closeConfigDetailsModal();
            // Run this step if the id does not equal null.
            if (id !== null) openConfigEditModal(id);
        }

        // Delete config from details.
        function deleteConfigFromDetails() {
            // Return early if the active config item id equals null.
            if (activeConfigItemId === null) return;
            openConfigDeleteModal(activeConfigItemId);
        }

        // Open config delete modal.
        function openConfigDeleteModal(id) {
            var item = findConfigItem(id);
            // Return early if the item is missing or false.
            if (!item) return;
            activeConfigItemId = id;
            var fields = configFieldMap[currentConfigType];
            document.getElementById('configDeleteMessage').textContent = 'Are you sure you want to move this configuration to Trash Bin?';
            document.getElementById('configDetailsModal').style.display = 'none';
            document.getElementById('configDeleteConfirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Close config delete modal.
        function closeConfigDeleteModal() {
            document.getElementById('configDeleteConfirmModal').style.display = 'none';
            activeConfigItemId = null;
            restoreSettingsBodyOverflow();
        }

        // Get the confirm delete config.
        function confirmDeleteConfig() {
            // Return early if the active config item id equals null.
            if (activeConfigItemId === null) return;
            var id = activeConfigItemId;
            var button = document.getElementById('confirmDeleteConfigBtn');
            setButtonLoading(button, true, 'Deleting...');
            // Use the result when this request finishes.
            fetch(configApiBase + '/' + currentConfigType + '/' + id, {
                credentials: 'same-origin', method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(function(res) {
                // Use the result when this request finishes.
                return res.json().then(function(data) { return { ok: res.ok, data: data }; });
            }).then(function(result) {
                // Run this step if the result ok is set and the success is set.
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

        // Get the restore settings body overflow.
        function restoreSettingsBodyOverflow() {
            var openModal = document.querySelector('.settings-page .modal-overlay[style*="display: flex"]');
            // Run this step if the open modal is missing or false.
            if (!openModal) document.body.style.overflow = '';
        }

        // Show config fields.
        function renderConfigFields(item) {
            var container = document.getElementById('configItemFields');
            var meta = configMeta[currentConfigType];
            var definitions = meta && meta.fields ? meta.fields : {};
            container.innerHTML = '';
            // Process each field.
            Object.keys(definitions).forEach(function(field) {
                var definition = definitions[field];
                var wrapper = document.createElement('div');
                wrapper.className = 'form-group';
                wrapper.style.marginBottom = '18px';
                var label = document.createElement('label');
                label.style.cssText = 'display:block;font-size:.85rem;font-weight:500;color:#333;margin-bottom:4px;';
                label.textContent = definition.label + (definition.required ? ' *' : '');
                var input;
                // Run this step if the definition type equals select.
                if (definition.type === 'select') {
                    input = document.createElement('select');
                    // Process each value.
                    Object.keys(definition.options || {}).forEach(function(value) {
                        var option = document.createElement('option');
                        option.value = value;
                        option.textContent = definition.options[value];
                        input.appendChild(option);
                    });
                } else {
                    input = document.createElement('input');
                    input.type = definition.type === 'number' ? 'number' : 'text';
                    // Run this step if the definition type equals number.
                    if (definition.type === 'number') {
                        input.min = definition.min === undefined ? '0' : String(definition.min);
                        input.step = definition.step === undefined ? '1' : String(definition.step);
                    } else {
                        input.maxLength = definition.max || 255;
                    }
                }
                input.id = 'configField_' + field;
                input.dataset.field = field;
                input.required = !!definition.required;
                input.style.cssText = 'width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:.95rem;background:#fafafa;';
                var value = item && item[field] !== null && item[field] !== undefined ? item[field] : '';
                // Run this step if the field equals is_active and the value equals an empty value.
                if (field === 'is_active' && value === '') value = '1';
                input.value = String(value === true ? 1 : (value === false ? 0 : value));
                wrapper.append(label, input);
                container.appendChild(wrapper);
            });
        }

        // Save config item.
        function saveConfigItem(btn) {
            var id = document.getElementById('configItemId').value;
            var payload = {};
            var invalid = false;
            // Process each input.
            document.querySelectorAll('#configItemFields [data-field]').forEach(function(input) {
                var value = input.value.trim();
                // Run this step if the required is set and the value is missing or false.
                if (input.required && !value) invalid = true;
                // Run this step if the value is set and the pattern is set and the test is missing or false.
                if (value && input.pattern && !new RegExp('^(?:' + input.pattern + ')$').test(value)) invalid = true;
                payload[input.dataset.field] = value;
            });
            // Run this step if the invalid is set.
            if (invalid) { alert('Complete every required field. Category codes must start with a letter and use only letters, numbers, spaces, hyphens, or underscores.'); return; }

            var url = configApiBase + '/' + currentConfigType;
            var method = 'POST';
            // Run this step if the id is set.
            if (id) {
                url += '/' + id;
                method = 'PATCH';
            }

            setButtonLoading(btn, true, 'Saving...');

            // Use the result when this request finishes.
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
                // Run this step if the result ok is set and the success is set.
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

        // Success notification
        function showSuccess(message) {
            var notif = document.getElementById('successNotification');
            var msgSpan = document.getElementById('successMessage');
            // Run this step if the msg span is set.
            if (msgSpan) {
                msgSpan.textContent = message || 'Saved successfully!';
            }
            notif.style.display = 'block';
            // Run this action after the delay.
            setTimeout(function() {
                closeSuccess();
            }, 5000);
        }

        // Close success.
        function closeSuccess() {
            document.getElementById('successNotification').style.display = 'none';
        }

        // Load system settings.
        function loadSystemSettings() {
            // Return early if the is admin is missing or false.
            if (!isAdmin) return;
            // Use the result when this request finishes.
            fetch('/api/settings', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function(res) { return res.json().then(function(data) { return { ok: res.ok, data: data }; }); })
                .then(function(result) {
                    // Return early if the result ok is missing or false or the success is missing or false.
                    if (!result.ok || !result.data.success) return;
                    var values = result.data.data || {};
                    var fields = {
                        inventory_reorder_threshold: 'inventoryReorderThreshold',
                        project_at_risk_days_before_end_date: 'projectAtRiskDays',
                        inventory_max_transaction_quantity: 'inventoryMaxTransactionQuantity'
                    };
                    // Process each key.
                    Object.keys(fields).forEach(function(key) {
                        var input = document.getElementById(fields[key]);
                        // Run this step if the input is set and the key does not equal the undefined.
                        if (input && values[key] !== undefined) input.value = values[key];
                    });
                }).catch(function(error) { console.error('Unable to load system settings.', error); });
        }

        // Save system settings.
        function saveSystemSettings(button) {
            var payload = {
                inventory_reorder_threshold: document.getElementById('inventoryReorderThreshold').value,
                project_at_risk_days_before_end_date: document.getElementById('projectAtRiskDays').value,
                inventory_max_transaction_quantity: document.getElementById('inventoryMaxTransactionQuantity').value
            };
            setButtonLoading(button, true, 'Saving...');
            // Use the result when this request finishes.
            fetch('/api/settings', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function(res) { return res.json().then(function(data) { return { ok: res.ok, data: data }; }); })
                .then(function(result) {
                    // Run this step if the result ok is set and the success is set.
                    if (result.ok && result.data.success) { loadSystemSettings(); showSuccess(result.data.message || 'System settings saved successfully.'); }
                    else alert(result.data.message || 'Unable to save system settings.');
                }).catch(function(error) { console.error(error); alert('Unable to save system settings.'); })
                .finally(function() { setButtonLoading(button, false); });
        }

        // Add user modal
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
            document.getElementById('addUserName').value = '';
            document.getElementById('addUserEmail').value = '';
            document.getElementById('addUserRole').value = 'admin';
        }

        // Close add user modal.
        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
            document.body.style.overflow = '';
        }

                // Save new user.
                function saveNewUser(btn) {
            var name = document.getElementById('addUserName').value.trim();
            var email = document.getElementById('addUserEmail').value.trim();
            var role = document.getElementById('addUserRole').value;
            // Run this step if the name is missing or false or the email is missing or false.
            if (!name || !email) {
                alert('Please fill in all required fields.');
                return;
            }

            var payload = { name: name, email: email, role: role, _token: csrfToken };

            setButtonLoading(btn, true, 'Adding...');

            // Use the result when this request finishes.
            fetch('/users', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                // Run this step if the success is set.
                if (data.success) {
                    closeAddUserModal();
                    // Show the initial-password instructions only after the user is created.
                    if (data.password) {
                        openPasswordModal(data.password, name);
                    }
                    refreshUsers();
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

        // Close modals on backdrop click
        document.getElementById('addUserModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) {
                closeAddUserModal();
            }
        });
        // Handle the click event.
        document.getElementById('configItemModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) {
                closeConfigItemModal();
            }
        });
        // Handle the click event.
        document.getElementById('configDetailsModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) closeConfigDetailsModal();
        });
        // Handle the click event.
        document.getElementById('configDeleteConfirmModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) closeConfigDeleteModal();
        });
        // Handle the click event.
        document.getElementById('userDetailsModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) closeUserDetails();
        });
        // Handle the click event.
        document.getElementById('userEditModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) closeUserEdit();
        });
        // Handle the keydown event.
        document.addEventListener('keydown', function(e) {
            // Return early if the key does not equal Escape.
            if (e.key !== 'Escape') return;
            // Return early if the style display equals flex.
            if (document.getElementById('emptyTrashConfirmModal').style.display === 'flex') return closeEmptyTrashModal();
            // Return early if the style display equals flex.
            if (document.getElementById('restoreTrashConfirmModal').style.display === 'flex') return closeTrashRestoreModal();
            // Return early if the style display equals flex.
            if (document.getElementById('configDeleteConfirmModal').style.display === 'flex') return closeConfigDeleteModal();
            // Return early if the style display equals flex.
            if (document.getElementById('configDetailsModal').style.display === 'flex') return closeConfigDetailsModal();
            // Return early if the style display equals flex.
            if (document.getElementById('configItemModal').style.display === 'flex') return closeConfigItemModal();
            // Return early if the style display equals flex.
            if (document.getElementById('userEditModal').style.display === 'flex') return closeUserEdit();
            // Return early if the style display equals flex.
            if (document.getElementById('userDetailsModal').style.display === 'flex') return closeUserDetails();
            // Return early if the style display equals flex.
            if (document.getElementById('addUserModal').style.display === 'flex') return closeAddUserModal();
        });
        // Open password modal.
        function openPasswordModal(password, username) {
            var field = document.getElementById('generatedPasswordField');
            // Run this step if the field is set.
            if (field) field.value = password || '';
            var modal = document.getElementById('passwordModal');
            // Run this step if the modal is set.
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }

        // Close password modal.
        function closePasswordModal(reload) {
            var modal = document.getElementById('passwordModal');
            // Run this step if the modal is set.
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
            // Run this step if the reload is set.
            if (reload) refreshUsers();
        }

        // Get the copy password.
        function copyPassword() {
            var field = document.getElementById('generatedPasswordField');
            // Return early if the field is missing or false.
            if (!field) return;
            // Try this action and handle any errors below.
            try {
                // Use the result when this request finishes.
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

        // Close success on click outside
        document.addEventListener('click', function(e) {
            // Run this step if the style display equals block.
            if (document.getElementById('successNotification').style.display === 'block') {
                // Run this step if the closest is missing or false.
                if (!e.target.closest('.success-notification')) {
                    closeSuccess();
                }
            }
        });

        // Init
        if (isAdmin) fetchConfigItems(currentConfigType);

        // Change password functions
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

        // Close change password modal.
        function closeChangePasswordModal() {
            document.getElementById('changePasswordModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        // Switch password visibility.
        function togglePasswordVisibility(inputId, button) {
            var input = document.getElementById(inputId);
            // Run this step if the input type equals password.
            if (input.type === 'password') {
                input.type = 'text';
                button.textContent = 'Hide';
            } else {
                input.type = 'password';
                button.textContent = 'Show';
            }
        }

        // Get the password icon.
        function passwordIcon(isVisible) {
            return isVisible
                ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a20.3 20.3 0 0 1 5.06-6.06M9.9 4.24A10.5 10.5 0 0 1 12 4c7 0 11 8 11 8a20.3 20.3 0 0 1-3.22 4.44M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
                : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>';
        }

        // Set password toggle icon.
        function setPasswordToggleIcon(button, isVisible) {
            // Return early if the button is missing or false.
            if (!button) return;
            button.innerHTML = passwordIcon(isVisible);
            button.classList.toggle('is-visible', isVisible);
            button.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
        }

        // Reset password toggle buttons.
        function resetPasswordToggleButtons() {
            // Process each button.
            document.querySelectorAll('[data-password-toggle]').forEach(function(button) {
                setPasswordToggleIcon(button, false);
            });
            // Process each id.
            ['currentPassword', 'newPassword', 'confirmPassword'].forEach(function(id) {
                var input = document.getElementById(id);
                // Run this step if the input is set.
                if (input) input.type = 'password';
            });
        }

        // Switch password visibility.
        function togglePasswordVisibility(inputId, button) {
            // Run this step if the event is set.
            if (window.event) window.event.preventDefault();
            var input = document.getElementById(inputId);
            // Return early if the input is missing or false.
            if (!input) return;
            var isVisible = input.type === 'password';
            input.type = isVisible ? 'text' : 'password';
            setPasswordToggleIcon(button, isVisible);
            input.focus({ preventScroll: true });
        }

                // Submit change password.
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
            
            // Run this step if the new password length is below 8.
            if (newPassword.length < 8) {
                document.getElementById('newPasswordError').textContent = 'Password must be at least 8 characters long';
                document.getElementById('newPasswordError').style.display = 'block';
                hasError = true;
            }
            
            // Run this step if the new password does not equal the confirm password.
            if (newPassword !== confirmPassword) {
                document.getElementById('confirmPasswordError').textContent = 'Passwords do not match';
                document.getElementById('confirmPasswordError').style.display = 'block';
                hasError = true;
            }
            
            // Return early if the has error is set.
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

            // Use the result when this request finishes.
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
                // Run this step if the success is set.
                if (data.success) {
                    closeChangePasswordModal();
                    showSuccess('Password updated successfully!');
                    // Update the "Last changed" text
                    var descElement = document.querySelector('.security-item .left .desc');
                    // Run this step if the desc element is set.
                    if (descElement) {
                        descElement.textContent = 'Last changed just now';
                    }
                } else {
                    // Run this step if the message is set and the includes check matches.
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

        // Close change password modal on backdrop click
        document.addEventListener('DOMContentLoaded', function() {
            var passwordModal = document.getElementById('changePasswordModal');
            // Run this step if the password modal is set.
            if (passwordModal) {
                // Handle the click event.
                passwordModal.addEventListener('click', function(e) {
                    // Run this step if the target equals the value.
                    if (e.target === this) {
                        closeChangePasswordModal();
                    }
                });
            }
        });

        // Handle the input event.
        document.getElementById('newPassword').addEventListener('input', function() {
            var password = this.value;
            var strengthIndicator = document.getElementById('passwordStrength');
            // Run this step if the strength indicator is missing or false.
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

        // Check password strength.
        function checkPasswordStrength(password) {
            var strength = {
                text: 'Weak',
                color: '#d32f2f'
            };
            
            // Run this step if the password length is at least 8.
            if (password.length >= 8) {
                var hasUpperCase = /[A-Z]/.test(password);
                var hasLowerCase = /[a-z]/.test(password);
                var hasNumbers = /\d/.test(password);
                var hasSymbols = /[!@#$%^&*(),.?":{}|<>]/.test(password);
                
                var score = 0;
                // Run this step if the has upper case is set.
                if (hasUpperCase) score++;
                // Run this step if the has lower case is set.
                if (hasLowerCase) score++;
                // Run this step if the has numbers is set.
                if (hasNumbers) score++;
                // Run this step if the has symbols is set.
                if (hasSymbols) score++;
                
                // Run this step if the score is at least 4.
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

        // Handle the dom content loaded event.
        document.addEventListener('DOMContentLoaded', function() {
            resetPasswordToggleButtons();
            loadSystemSettings();
            var query = new URLSearchParams(window.location.search);
            // Run this step if the get equals defaultfilters.
            if (query.get('section') === 'defaultfilters') {
                var defaultFiltersTab = document.querySelector(".settings-nav li[onclick*='defaultfilters']");
                // Run this step if the default filters tab is set.
                if (defaultFiltersTab) switchSettings(defaultFiltersTab, 'defaultfilters');
            } else if (query.get('section') === 'usermanagement') {
                var userManagementTab = document.querySelector(".settings-nav li[onclick*='usermanagement']");
                // Run this step if the user management tab is set.
                if (userManagementTab) switchSettings(userManagementTab, 'usermanagement');
            } else if (query.get('change_password') === '1') {
                var securityTab = document.querySelector(".settings-nav li[onclick*='security']");
                // Run this step if the security tab is set.
                if (securityTab) switchSettings(securityTab, 'security');
                openChangePasswordModal();
            }
            loadDefaultFilters();
        });

        var savedDefaultFilters = {};
        var defaultFilterDefinitions = {
            dashboard: [['status','Project status','select',['','Ongoing','Pending','At Risk','Delayed','Completed']],['stockStatus','Stock status','select',['','In stock','Low stock','Out of stock']]],
            projects: [['projectStatusFilter','Status','select',['','Ongoing','Pending','At Risk','Delayed','Completed']],['projectPhaseFilter','Phase','lookup'],['projectDateFrom','From','date'],['projectDateTo','To','date']],
            'finance.expenses': [['projectFilter','Project','lookup'],['expenseScopeFilter','Expense type','select',['all','direct','admin','office','overall']],['expenseRecordStatusFilter','Record status','select',['all','missing_amount','no_project','missing_amount_and_project']],['expenseSourceFilter','Expense source','select',['all','inventory','manual']],['expenseCategoryFilter','Category','lookup']],
            'finance.budgets': [['budgetProjectFilter','Project','lookup'],['budgetStatusFilter','Status','select',['all','On Track','Near Limit','Over Budget','No Budget']]],
            'finance.bonds': [['bondProjectFilter','Project','lookup'],['bondStatusFilter','Status','select',['all','active','released','forfeited']]],
            'inventory.items': [['itemsCategoryFilter','Category','lookup'],['itemsSupplierFilter','Supplier','lookup'],['itemsStockFilter','Stock status','select',['all','in_stock','low_stock','out_of_stock']]],
            'inventory.transactions': [['typeFilter','Transaction type','select',['all','IN','OUT']],['transactionCategoryFilter','Category','lookup'],['transactionProjectFilter','Project','lookup'],['startDate','From','date'],['endDate','To','date']],
            suppliers: [['supplierSort','Sort order','select',['name','items','alerts']]],
            reports: [['filterProject','Project','lookup'],['filterStatus','Status','lookup'],['filterClassification','Classification','lookup'],['filterCategory','Category','lookup'],['filterSupplier','Supplier','lookup'],['filterStockStatus','Stock status','lookup'],['filterStart','From','date'],['filterEnd','To','date']],
            'analytics.material': [['materialForecastStatus','Status','select',['','Healthy','Low Stock','Reorder Needed']]],
            'analytics.budget': [['budgetVarianceProject','Project','lookup'],['budgetVarianceStatus','Position','select',['','within','over']]]
        };
        var defaultFilterLookups = {
            projectPhaseFilter: ['/api/project-phases', 'phase_name', 'phase_name'],
            projectFilter: ['/api/projects/list', 'project_name', 'project_name'],
            budgetProjectFilter: ['/api/projects/list', 'project_name', 'project_name'],
            bondProjectFilter: ['/api/projects/list', 'project_id', 'project_name'],
            expenseCategoryFilter: ['/api/finance-categories', 'fin_category_id', 'category_name'],
            itemsCategoryFilter: ['/api/inventory-categories', 'inventory_category_id', 'inventory_category_name'],
            transactionCategoryFilter: ['/api/inventory-categories', 'inventory_category_id', 'inventory_category_name'],
            itemsSupplierFilter: ['/api/suppliers', 'supplier_id', 'supplier_name'],
            transactionProjectFilter: ['/api/projects/list', 'project_id', 'project_name'],
            filterProject: ['/api/projects/list', 'project_id', 'project_name'],
            filterStatus: ['/api/reports/catalog', null, null, 'statuses'],
            filterClassification: ['/api/reports/catalog', null, null, 'classifications'],
            filterCategory: ['/api/reports/catalog', 'value', 'label', 'categories'],
            filterSupplier: ['/api/suppliers', 'supplier_id', 'supplier_name'],
            filterStockStatus: ['/api/reports/catalog', null, null, 'stock_statuses'],
            budgetVarianceProject: ['/api/projects/list', 'project_name', 'project_name']
        };

        // Load default filters.
        function loadDefaultFilters() {
            // Use the result when this request finishes.
            fetch('/api/default-filters', { headers: { Accept: 'application/json' } })
                .then(function(response) { if (!response.ok) throw new Error('Unable to load defaults.'); return response.json(); })
                .then(function(data) { savedDefaultFilters = data || {}; renderDefaultFilterFields(); })
                .catch(function(error) { document.getElementById('defaultFilterStatus').textContent = error.message; });
        }

        // Show default filter fields.
        function renderDefaultFilterFields() {
            var module = document.getElementById('defaultFilterModule').value;
            var values = savedDefaultFilters[module] || {};
            var host = document.getElementById('defaultFilterFields');
            host.replaceChildren();
            // Process each definition.
            (defaultFilterDefinitions[module] || []).forEach(function(definition) {
                var label = document.createElement('label');
                var title = document.createElement('span');
                var input = ['select', 'lookup'].includes(definition[2]) ? document.createElement('select') : document.createElement('input');
                title.textContent = definition[1];
                // Run this step if the input tag name equals INPUT.
                if (input.tagName === 'INPUT') input.type = definition[2];
                else (definition[2] === 'lookup' ? [''] : (definition[3] || [])).forEach(function(value) {
                    var option = document.createElement('option');
                    option.value = value;
                    option.textContent = value || 'No default';
                    input.appendChild(option);
                });
                input.dataset.filterKey = definition[0];
                var savedValue = values[definition[0]] || '';
                // Run this step if the module equals dashboard and the 0 equals stockStatus.
                if (module === 'dashboard' && definition[0] === 'stockStatus') {
                    savedValue = {in_stock: 'In stock', low_stock: 'Low stock', out_of_stock: 'Out of stock'}[savedValue] || savedValue;
                }
                // Run this step if the 2 equals lookup and the saved value does not equal an empty value.
                if (definition[2] === 'lookup' && savedValue !== '') {
                    var savedOption = document.createElement('option');
                    savedOption.value = savedValue;
                    savedOption.textContent = 'Saved: ' + savedValue;
                    input.appendChild(savedOption);
                }
                input.value = savedValue;
                // Run this step if the input tag name equals INPUT.
                if (input.tagName === 'INPUT') input.placeholder = 'Leave blank for no default';
                label.append(title, input);
                host.appendChild(label);
                // Run this step if the 2 equals lookup.
                if (definition[2] === 'lookup') {
                    var lookup = defaultFilterLookups[definition[0]];
                    // Use the result when this request finishes.
                    fetch(lookup[0], {headers: {'Accept':'application/json'}})
                        .then(function(response) { if (!response.ok) throw new Error('Unable to load filter options.'); return response.json(); })
                        .then(function(data) {
                            var rows = lookup[3] ? (data.options?.[lookup[3]] || [])
                                : (Array.isArray(data) ? data : (Array.isArray(data.data) ? data.data : []));
                            var selectedValue = input.value;
                            input.replaceChildren();
                            var blank = document.createElement('option');
                            blank.value = '';
                            blank.textContent = 'No default';
                            input.appendChild(blank);
                            // Process each row.
                            rows.forEach(function(row) {
                                var option = document.createElement('option');
                                option.value = String(lookup[1] ? row[lookup[1]] : row);
                                option.textContent = lookup[2] ? row[lookup[2]] : row;
                                input.appendChild(option);
                            });
                            // Run this step if the saved value does not equal an empty value and the some is missing or false.
                            if (savedValue !== '' && !Array.from(input.options).some(function(option) { return option.value === savedValue; })) {
                                var missing = document.createElement('option');
                                missing.value = savedValue;
                                missing.textContent = 'Saved: ' + savedValue + ' (unavailable)';
                                input.appendChild(missing);
                            }
                            input.value = selectedValue;
                        })
                        .catch(function() { document.getElementById('defaultFilterStatus').textContent = 'Some filter options could not be loaded.'; });
                }
            });
            // Run the some action.
            var hasConfiguredDefault = (defaultFilterDefinitions[module] || []).some(function(definition) {
                return values[definition[0]] !== undefined && values[definition[0]] !== '';
            });
            document.getElementById('defaultFilterStatus').textContent = hasConfiguredDefault ? 'Saved defaults are active for this module.' : 'No defaults saved for this module.';
        }

        // Save default filters.
        function saveDefaultFilters() {
            var module = document.getElementById('defaultFilterModule').value;
            var filters = {};
            // Process each input.
            document.querySelectorAll('#defaultFilterFields [data-filter-key]').forEach(function(input) {
                // Run this step if the trim does not equal an empty value.
                if (String(input.value).trim() !== '') filters[input.dataset.filterKey] = String(input.value).trim();
            });
            // Use the result when this request finishes.
            fetch('/api/default-filters/' + encodeURIComponent(module), {
                method: 'PUT', headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({filters: filters})
            }).then(function(response) { if (!response.ok) throw new Error('Unable to save defaults.'); return response.json(); })
              .then(function(data) { savedDefaultFilters[module] = data.filters || {}; renderDefaultFilterFields(); showSuccess('Default filters saved.'); })
              .catch(function(error) { document.getElementById('defaultFilterStatus').textContent = error.message; });
        }

        // Remove default filters.
        function removeDefaultFilters() {
            var module = document.getElementById('defaultFilterModule').value;
            // Use the result when this request finishes.
            fetch('/api/default-filters/' + encodeURIComponent(module), {method:'DELETE',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrfToken}})
                .then(function(response) { if (!response.ok) throw new Error('Unable to remove defaults.'); delete savedDefaultFilters[module]; renderDefaultFilterFields(); showSuccess('Saved defaults removed.'); })
                .catch(function(error) { document.getElementById('defaultFilterStatus').textContent = error.message; });
        }
    </script>
    {{-- Set up the actions used by this page section. --}}
    <script data-login-epoch="{{ session('navigation_epoch', 'initial') }}" src="{{ asset('js/pfims-system-ui.js') }}?v={{ filemtime(public_path('js/pfims-system-ui.js')) }}"></script>
    @include('partials.validation-alert')
</body>
</html>
