{{-- Prepare the values used by this page section. --}}
@php $portal = $portal ?? 'admin'; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Notifications - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/notifications.css') }}">
    {{-- Set the styles used by this page section. --}}
    <style>
        /* Style the error notification. */
        .error-notification { z-index: 9999 !important; }
        /* Style the success notification. */
        .success-notification { z-index: 9999 !important; }
        /* Style the confirm modal. */
        #confirmModal { z-index: 9999 !important; }
        
        /* Notification icon colors */
        .notif-icon.green { background: #e8f5e9; color: #2e7d32; }
        /* Style the notif icon orange. */
        .notif-icon.orange { background: #fff3e0; color: #e65100; }
        /* Style the notif icon red. */
        .notif-icon.red { background: #ffebee; color: #c62828; }
        /* Style the notif icon blue. */
        .notif-icon.blue { background: #e3f2fd; color: #0d47a1; }
        /* Style the notif icon wrench. */
        .notif-icon.wrench { background: #f5f5f5; color: #616161; }
        /* Style the notif icon purple. */
        .notif-icon.purple { background: #f3e5f5; color: #6a1b9a; }
        
        /* Style the notif item unread. */
        .notif-item.unread,
        .notif-item.read {
            background: #f0f7ff;
            border-left: 4px solid #1a237e;
            opacity: 1;
        }
        
        /* Style the notif item notif time. */
        .notif-item .notif-time {
            font-size: 0.7rem;
            color: #aaa;
            margin-top: 4px;
        }
        
        /* Style the empty state. */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #aaa;
        }
        
        /* Style the empty state empty icon. */
        .empty-state .empty-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        /* Style the notif actions. */
        .notif-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Total notification cards */
        .total-notif-item {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border-left: 4px solid #c9a96e;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        /* Style the total notif item on hover. */
        .total-notif-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }
        
        /* Style the total notif item total header. */
        .total-notif-item .total-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Style the total notif item total title. */
        .total-notif-item .total-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1a2b3c;
        }
        
        /* Style the total notif item total count. */
        .total-notif-item .total-count {
            font-size: 1.2rem;
            font-weight: 700;
            color: #c9a96e;
            background: #f8f6f2;
            padding: 2px 12px;
            border-radius: 12px;
        }
        
        /* Style the total count red. */
        .total-notif-item .total-count.red { color: #d32f2f; background: #ffebee; }
        /* Style the total count orange. */
        .total-notif-item .total-count.orange { color: #e65100; background: #fff3e0; }
        /* Style the total count green. */
        .total-notif-item .total-count.green { color: #2e7d32; background: #e8f5e9; }
        /* Style the total count blue. */
        .total-notif-item .total-count.blue { color: #1565c0; background: #e3f2fd; }
        
        /* Style the total notif item total message. */
        .total-notif-item .total-message {
            font-size: 0.8rem;
            color: #888;
            margin-top: 4px;
        }
        
        /* Style the total notif item total time. */
        .total-notif-item .total-time {
            font-size: 0.7rem;
            color: #aaa;
            margin-top: 6px;
        }
        
                /* Style the total notif item card red. */
                .total-notif-item.card-red { border-left-color: #d32f2f; }
        /* Style the total notif item card orange. */
        .total-notif-item.card-orange { border-left-color: #e65100; }
        /* Style the total notif item card green. */
        .total-notif-item.card-green { border-left-color: #2e7d32; }
        /* Style the total notif item card blue. */
        .total-notif-item.card-blue { border-left-color: #1565c0; }

        /* Style the notif actions. */
        .notif-actions {
            align-items: center;
        }

        /* Style the notif actions btn action icon. */
        .notif-actions .btn-action-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            padding: 0;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #fff;
            cursor: pointer;
            transition: 0.3s;
        }

        /* Style the notif actions btn action icon. */
        .notif-actions .btn-action-icon img {
            width: 18px;
            height: 18px;
            object-fit: contain;
        }

        /* Style the notif actions btn action icon on hover. */
        .notif-actions .btn-action-icon:hover {
            background: #f5f5f5;
            border-color: #c9a96e;
        }

        /* Style the notif actions btn action icon. */
        .notif-actions .btn-action-icon:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Style the confirm modal modal container. */
        #confirmModal .modal-container {
            width: 400px;
            max-width: 95%;
        }
    </style>
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
<body class="notifications-page" data-portal="{{ $portal }}">

        <!-- Error notification (pop-up) -->
    <div id="errorNotification" class="error-notification" style="display: none;">
        <div class="error-content">
            <span class="error-icon">!</span>
            <span id="errorMessage">An error occurred. Please try again.</span>
            <button class="error-close" onclick="closeError()">×</button>
        </div>
    </div>

    <!-- Success notification (pop-up) -->
    <div id="successNotification" class="success-notification" style="display: none;">
        <div class="success-content">
            <span class="success-icon">●</span>
            <span id="successMessage">Action completed successfully!</span>
            <button class="success-close" onclick="closeSuccess()">×</button>
        </div>
    </div>

    <!-- Confirm modal (for clear all) -->
    <div id="confirmModal" class="modal-overlay pfims-delete-confirm" style="display: none; z-index: 9999;">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="modal-close" onclick="closeConfirmModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="confirmMessage" style="font-size: 1rem; color: #333; margin-bottom: 10px;">
                    Are you sure you want to move all notifications to Trash Bin?
                </p>
                <p style="font-size: 0.85rem; color: #888; margin-bottom: 20px;">
                    You can restore deleted records from Settings → Trash Bin for 30 days before permanent deletion.
                </p>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: center; gap: 12px; margin-top: 10px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                <button class="btn-cancel" onclick="closeConfirmModal()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: transparent; color: #888; transition: 0.3s;">Cancel</button>
                <button class="btn-delete" id="confirmActionBtn" onclick="confirmAction()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: #d32f2f; color: #fff; transition: 0.3s;">Delete</button>
            </div>
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
            <a href="{{ url('/notifications') }}" onclick="hideBadge(event)" style="position: relative;">
                <img src="{{ asset('images/notif.jpg') }}" style="height: 22px; width: auto; cursor: pointer;">
                <span>Notifications</span>
                <span class="notif-badge" id="notifBadge">6</span>
            </a>
            <a href="{{ url('/profile') }}" style="display: flex; align-items: center; gap: 5px; color: inherit; text-decoration: none;">
                <img src="{{ asset('images/user.jpg') }}" alt="User" style="height: 30px; width: 30px; cursor: pointer; border-radius: 50%; object-fit: cover;">
                <span>{{ auth()->user()->name }}</span>
            </a>
        </div>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar">
                {{-- Show the page navigation. --}}
                <nav>
            <ul>
                <li><a href="{{ url('/dashboard') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/dashboard.png') }}" alt="" class="nav-link-icon">DASHBOARD</a></li>
                <li><a href="{{ url('/projects') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/projects.png') }}" alt="" class="nav-link-icon">PROJECTS</a></li>
                <li><a href="{{ url('/finance') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/finance.png') }}" alt="" class="nav-link-icon">FINANCE</a></li>
                <li><a href="{{ url('/inventory') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/inventory.png') }}" alt="" class="nav-link-icon">INVENTORY</a></li>
                <li><a href="{{ url('/reports') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/folder.svg') }}" alt="" class="nav-link-icon">REPORTS</a></li>
            </ul>
        </nav>
        <div class="bottom-nav">
            <ul>
                <li>
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
            <div>
                <h1>NOTIFICATIONS</h1>
            </div>
                        <div class="notif-actions">
                <button class="btn-mark-read" onclick="markAllRead()">✓ Mark all read</button>
                <button class="btn-clear-all" onclick="openClearAllModal()">✕ Clear all</button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="notif-tabs">
            <span class="tab active" onclick="switchTab(this, 'all')">All</span>
            <span class="tab" onclick="switchTab(this, 'alerts')">Alerts</span>
            <span class="tab" onclick="switchTab(this, 'system')">System</span>
        </div>

                <!-- Notifications list -->
        <div id="notifList">
            <div style="text-align: center; padding: 40px; color: #888;">Loading notifications...</div>
        </div>

        <!-- Pagination -->
        <div class="pagination-wrapper" id="notifPaginationWrapper" style="display: none;">
            <div class="rows-info">
                Rows per page
                <select id="notifRowsPerPage" aria-label="Notification rows per page" onchange="changeNotifPageSize()">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span id="notifTotalCount" class="pagination-total">Total: 0</span>
            </div>
            {{-- Show the notif pagination links section. --}}
            <div class="pagination-links" id="notifPaginationLinks">
                <!-- Generated by JavaScript -->
            </div>
        </div>

    </main>

    {{-- Set up the actions used by this page section. --}}
    <script>
                var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        var allNotifications = [];
        var currentTab = 'all';
        // Escape text so it can be shown safely in HTML.
        function escapeHtml(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function(c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
        var showOnlyTotals = false;
        var notifPageSize = 10;
        var notifCurrentPage = 1;

        // Category muting from the original file, combined with the E-version
        // acknowledgement flow. Mandatory notices are never hidden.
        var MUTABLE_TYPES = {
            project_updates: ['project_delayed', 'project_at_risk', 'project_delayed_total', 'project_at_risk_total', 'project_past_deadline_total'],
            budget_alerts: ['budget_expense_overrun_total', 'new_expense', 'new_budget', 'budget_updated'],
            inventory_alerts: ['item_low_stock', 'item_low_stock_total', 'item_out_of_stock', 'stock_in_expense']
        };
        // Filter muted notifications.
        function filterMutedNotifications(list) {
            var mutedTypes = [];
            // Process each category.
            Object.keys(MUTABLE_TYPES).forEach(function(category) {
                // Run this step if the get item equals true.
                if (localStorage.getItem('notif_mute_' + category) === 'true') mutedTypes = mutedTypes.concat(MUTABLE_TYPES[category]);
            });
            // Keep the matching n items.
            return list.filter(function(n) { return n.requires_acknowledgement || mutedTypes.indexOf(n.type) === -1; });
        }

        // Hide notification badge
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            // Run this step if the badge is set.
            if (badge) {
                badge.style.display = 'none';
            }
        }

        // Confirm modal
        var confirmCallback = null;

        // Open clear all modal.
        function openClearAllModal() {
            document.getElementById('confirmMessage').textContent = 'Are you sure you want to move all notifications to Trash Bin?';
            confirmCallback = async function() {
                // Try this action and handle any errors below.
                try {
                    var response = await fetch('/api/notifications', {method:'DELETE',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}});
                    var result=await response.json();
                    // Run this step if the response ok is missing or false.
                    if (!response.ok) throw new Error(result.message || 'Unable to clear notifications.');
                    showOnlyTotals=false; await loadNotifications();
                    showSuccess(result.message || 'Notifications moved to Trash Bin for 30 days.');
                } catch(error) { showError(error.message); }
            };
            document.getElementById('confirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Close confirm modal.
        function closeConfirmModal() {
            document.getElementById('confirmModal').style.display = 'none';
            document.body.style.overflow = '';
            confirmCallback = null;
        }

        // Get the confirm action.
        function confirmAction() {
            // Run this step if the confirm callback equals function.
            if (typeof confirmCallback === 'function') {
                confirmCallback();
            }
            closeConfirmModal();
        }

        // Handle the click event.
        document.getElementById('confirmModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) {
                closeConfirmModal();
            }
        });

                // Tab switching
        function switchTab(el, type) {
            var tabs = document.querySelectorAll('.notif-tabs .tab');
            // Process each tab.
            tabs.forEach(function(tab) {
                tab.classList.remove('active');
            });
            el.classList.add('active');
            currentTab = type;
            notifCurrentPage = 1;
            renderNotifications();
        }

        // Get notification icon based on kind
        function getNotificationIcon(kind) {
            var iconMap = {
                'warning': { class: 'orange', text: 'Warning' },
                'overdue': { class: 'red', text: 'Due' },
                'success': { class: 'green', text: 'Success' },
                'info': { class: 'blue', text: 'Info' },
                'maintenance': { class: 'wrench', text: 'Service' },
                'system_update': { class: 'purple', text: 'Update' }
            };
            return iconMap[kind] || { class: 'blue', text: 'Notice' };
        }

        // Get total notifications by type
        function getTotalNotifications() {
            var totals = [];
            var types = {};

            // Process each n.
            allNotifications.forEach(function(n) {
                // Run this step if the n type is set and the ends with check matches.
                if (n.type && n.type.endsWith('_total')) {
                    // Run this step if the n type is missing or false.
                    if (!types[n.type]) {
                        types[n.type] = {
                            type: n.type,
                            title: n.title,
                            message: n.message,
                            kind: n.kind,
                            filter: n.filter,
                            count: 0,
                            created_at: n.created_at,
                            is_read: n.is_read
                        };
                    }
                    types[n.type].count++;
                }
            });

            return Object.values(types);
        }

                // Load notifications
        function loadNotifications() {
            var list = document.getElementById('notifList');
            list.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">Loading notifications...</div>';

            // Use the result when this request finishes.
            return fetch('/api/notifications', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function(response) {
                // Run this step if the response ok is missing or false.
                if (!response.ok) throw new Error('Failed to load notifications');
                return response.json();
            })
            .then(function(data) {
                allNotifications = filterMutedNotifications(data.notifications || []);
                updateBadgeCount();
                renderNotifications();
            })
                        .catch(function(error) {
                console.error('Error loading notifications:', error);
                loadMockNotifications();
            });
        }

        // Load mock notifications
        function loadMockNotifications() {
            var now = new Date();
            allNotifications = [
                {
                    notification_id: 1,
                    title: 'Delayed Projects Need Attention',
                    message: '115 delayed projects need attention.',
                    type: 'project_delayed_total',
                    kind: 'overdue',
                    filter: 'alerts',
                    is_read: false,
                    created_at: new Date(now.getTime() - 1000 * 60 * 5).toISOString()
                },
                {
                    notification_id: 2,
                    title: 'Projects At Risk',
                    message: '50 projects are currently at risk.',
                    type: 'project_at_risk_total',
                    kind: 'warning',
                    filter: 'alerts',
                    is_read: false,
                    created_at: new Date(now.getTime() - 1000 * 60 * 30).toISOString()
                },
                {
                    notification_id: 3,
                    title: 'Projects Past Estimated Deadline',
                    message: '38 active projects are past the estimated deadline.',
                    type: 'project_past_deadline_total',
                    kind: 'overdue',
                    filter: 'alerts',
                    is_read: false,
                    created_at: new Date(now.getTime() - 1000 * 60 * 60).toISOString()
                },
                {
                    notification_id: 4,
                    title: 'Budget / Expense Overrun',
                    message: '988 project budgets are near or over the spending limit.',
                    type: 'budget_expense_overrun_total',
                    kind: 'warning',
                    filter: 'alerts',
                    is_read: false,
                    created_at: new Date(now.getTime() - 1000 * 60 * 60 * 2).toISOString()
                },
                {
                    notification_id: 5,
                    title: 'Low Stock Items',
                    message: '256 items are at or below the reorder threshold.',
                    type: 'item_low_stock_total',
                    kind: 'warning',
                    filter: 'alerts',
                    is_read: false,
                    created_at: new Date(now.getTime() - 1000 * 60 * 60 * 3).toISOString()
                }
            ];
            allNotifications = filterMutedNotifications(allNotifications);
            updateBadgeCount();
            renderNotifications();
        }

        // Update badge count
        function updateBadgeCount() {
            // Keep the matching n items.
            var unread = allNotifications.filter(function(n) { return !n.is_read; }).length;
            var badge = document.getElementById('notifBadge');
            // Run this step if the unread is above 0.
            if (unread > 0) {
                badge.textContent = unread;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }

                // Render notifications
        function renderNotifications() {
            var list = document.getElementById('notifList');
            var paginationWrapper = document.getElementById('notifPaginationWrapper');
            var filtered = allNotifications;

            // Filter by tab
            if (currentTab === 'alerts') {
                // Keep the matching n items.
                filtered = filtered.filter(function(n) { 
                    return n.filter === 'alerts' || n.kind === 'alert' || n.kind === 'warning' || n.kind === 'overdue'; 
                });
            } else if (currentTab === 'system') {
                // Keep the matching n items.
                filtered = filtered.filter(function(n) { 
                    return n.filter === 'system' || n.kind === 'system' || n.kind === 'system_update' || n.kind === 'maintenance'; 
                });
            }

            // If showing only totals, display grouped notifications
            if (showOnlyTotals) {
                paginationWrapper.style.display = 'none';
                var totals = getTotalNotifications();
                
                // Filter totals by tab
                if (currentTab === 'alerts') {
                    // Keep the matching n items.
                    totals = totals.filter(function(n) { 
                        return n.filter === 'alerts' || n.kind === 'alert' || n.kind === 'warning' || n.kind === 'overdue'; 
                    });
                } else if (currentTab === 'system') {
                    // Keep the matching n items.
                    totals = totals.filter(function(n) { 
                        return n.filter === 'system' || n.kind === 'system' || n.kind === 'system_update' || n.kind === 'maintenance'; 
                    });
                }

                // Run this step if the totals length equals 0.
                if (totals.length === 0) {
                    list.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-icon">Notifications</div>
                            <div style="font-size: 1.1rem; color: #666;">No notifications</div>
                            <div style="font-size: 0.85rem; color: #aaa; margin-top: 5px;">You're all caught up!</div>
                        </div>
                    `;
                    return;
                }

                var html = '';
                // Process each total.
                totals.forEach(function(total) {
                    var icon = getNotificationIcon(total.kind);
                    var cardClass = total.kind === 'overdue' ? 'card-red' : 
                                   total.kind === 'warning' ? 'card-orange' : 
                                   total.kind === 'success' ? 'card-green' : 'card-blue';
                    var countClass = total.kind === 'overdue' ? 'red' : 
                                    total.kind === 'warning' ? 'orange' : 
                                    total.kind === 'success' ? 'green' : 'blue';
                    
                    var date = new Date(total.created_at);
                    var timeStr = date.toLocaleString('en-US', { 
                        month: 'short', 
                        day: 'numeric', 
                        year: 'numeric',
                        hour: '2-digit', 
                        minute: '2-digit' 
                    });

                    html += `
                        <div class="total-notif-item ${cardClass}" onclick="expandTotal('${total.type}')">
                            <div class="total-header">
                                <span class="total-title">${icon.text} ${escapeHtml(total.title)}</span>
                                <span class="total-count ${countClass}">${total.count}</span>
                            </div>
                            <div class="total-message">${escapeHtml(total.message)}</div>
                            <div class="total-time">Updated: ${timeStr}</div>
                        </div>
                    `;
                });

                list.innerHTML = html;
                return;
            }

            // Show individual notifications
            // Sort by date (newest first)
            filtered.sort(function(a, b) {
                return new Date(b.created_at) - new Date(a.created_at);
            });

            // Run this step if the filtered length equals 0.
            if (filtered.length === 0) {
                paginationWrapper.style.display = 'none';
                list.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">Notifications</div>
                        <div style="font-size: 1.1rem; color: #666;">No notifications</div>
                        <div style="font-size: 0.85rem; color: #aaa; margin-top: 5px;">You're all caught up!</div>
                    </div>
                `;
                return;
            }

            var totalFiltered = filtered.length;
            var totalPages = Math.ceil(totalFiltered / notifPageSize);
            // Run this step if the notif current page is above the total pages.
            if (notifCurrentPage > totalPages) notifCurrentPage = totalPages || 1;
            var start = (notifCurrentPage - 1) * notifPageSize;
            var pageData = filtered.slice(start, start + notifPageSize);

            paginationWrapper.style.display = 'flex';
            document.getElementById('notifTotalCount').textContent = 'Total: ' + totalFiltered;
            renderNotifPaginationLinks(totalPages);

            var html = '';
            var currentDate = '';

            // Process each notification.
            pageData.forEach(function(notification) {
                var date = new Date(notification.created_at);
                var dateStr = date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
                var isToday = new Date().toDateString() === date.toDateString();
                var isYesterday = new Date(Date.now() - 86400000).toDateString() === date.toDateString();

                var sectionTitle = isToday ? 'TODAY' : (isYesterday ? 'YESTERDAY' : dateStr);

                // Run this step if the section title does not equal the current date.
                if (sectionTitle !== currentDate) {
                    // Run this step if the current date does not equal an empty value.
                    if (currentDate !== '') {
                        html += '</div>';
                    }
                    html += `<div class="notif-section"><div class="section-title">${sectionTitle}</div>`;
                    currentDate = sectionTitle;
                }

                var icon = getNotificationIcon(notification.kind);
                var readClass = notification.is_read ? 'read' : 'unread';
                var timeStr = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

                html += `
                    <div class="notif-item ${readClass}" data-id="${notification.notification_id}" ${notification.requires_acknowledgement ? '' : 'onclick="markAsRead(this)"'}>
                        <div class="notif-icon ${icon.class}">${icon.text}</div>
                        <div class="notif-content">
                            <div class="notif-title">${escapeHtml(notification.title)}</div>
                            <div class="notif-desc">${escapeHtml(notification.message)}</div>
                            <div class="notif-time">${isToday ? 'Today' : isYesterday ? 'Yesterday' : dateStr} at ${timeStr}</div>
                            ${notification.requires_acknowledgement ? '<button type="button" style="margin-top:10px; padding:8px 14px; border:0; border-radius:8px; background:#c9a96e; color:#fff; font-weight:600; cursor:pointer;" onclick="event.stopPropagation(); window.pfimsNavigate(\'/settings?section=security\')">Change Password</button>' : ''}
                        </div>
                        ${!notification.is_read ? '<div style="width: 8px; height: 8px; background: #1a237e; border-radius: 50%; flex-shrink: 0; margin-left: 10px;"></div>' : ''}
                    </div>
                `;
            });

            html += '</div>';
            list.innerHTML = html;
        }

        // Notification pagination
        function renderNotifPaginationLinks(totalPages) {
            var container = document.getElementById('notifPaginationLinks');
            var current = notifCurrentPage;

            // Run this step if the total pages is at most 1.
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }

            var html = '';
            html += '<a href="#" onclick="goToNotifPage(' + (current - 1) + '); return false;" class="' + (current <= 1 ? 'disabled' : '') + '">«</a>';

            // Repeat for each step in this range.
            for (var i = 1; i <= totalPages; i++) {
                html += '<a href="#" onclick="goToNotifPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
            }

            html += '<a href="#" onclick="goToNotifPage(' + (current + 1) + '); return false;" class="' + (current >= totalPages ? 'disabled' : '') + '">»</a>';
            container.innerHTML = html;
        }

        // Get the go to notif page.
        function goToNotifPage(page) {
            // Return early if the page is below 1.
            if (page < 1) return;
            notifCurrentPage = page;
            renderNotifications();
        }

        // Get the change notif page size.
        function changeNotifPageSize() {
            var select = document.getElementById('notifRowsPerPage');
            notifPageSize = parseInt(select.value) || 10;
            notifCurrentPage = 1;
            renderNotifications();
        }

                // Expand total to show individual notifications
        function expandTotal(type) {
            showOnlyTotals = false;
            notifCurrentPage = 1;
            // Reload to get individual notifications back
            loadNotifications();
        }

        // Mark as read
        function markAsRead(element) {
            var id = element.dataset.id;
            // Return early if the id is missing or false.
            if (!id) return;

            // Return early if the contains check matches.
            if (element.classList.contains('read')) return;

            // Use the result when this request finishes.
            fetch('/api/notifications/' + id + '/read', {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function(response) {
                // Run this step if the response ok is missing or false.
                if (!response.ok) throw new Error('Failed to mark as read');
                return response.json();
            })
            .then(function(data) {
                // Run this step if the success is set.
                if (data.success) {
                    // Run this step if the removed is set.
                    if (data.removed) {
                        // Keep the matching n items.
                        allNotifications = allNotifications.filter(function(n) { return n.notification_id != id; });
                        renderNotifications();
                        updateBadgeCount();
                        showSuccess('Reminder acknowledged.');
                        return;
                    }
                    element.classList.remove('unread');
                    element.classList.add('read');
                    var dot = element.querySelector('.notif-icon + .notif-content + div');
                    // Run this step if the dot is set.
                    if (dot) dot.remove();
                    // Run the find action.
                    var notif = allNotifications.find(function(n) { return n.notification_id == id; });
                    // Run this step if the notif is set.
                    if (notif) notif.is_read = true;
                    updateBadgeCount();
                }
            })
            .catch(function(error) {
                console.error('Error marking notification as read:', error);
                element.classList.remove('unread');
                element.classList.add('read');
                var dot = element.querySelector('.notif-icon + .notif-content + div');
                // Run this step if the dot is set.
                if (dot) dot.remove();
                // Run the find action.
                var notif = allNotifications.find(function(n) { return n.notification_id == id; });
                // Run this step if the notif is set.
                if (notif) notif.is_read = true;
                updateBadgeCount();
            });
        }

        // Mark all read
        function markAllRead() {
            // Keep the matching n items.
            var unreadIds = allNotifications.filter(function(n) { return !n.is_read && !n.requires_acknowledgement; }).map(function(n) { return n.notification_id; });
            
            // Run this step if the unread ids length equals 0.
            if (unreadIds.length === 0) {
                showSuccess('All notifications are already read!');
                return;
            }

            // Use the result when this request finishes.
            fetch('/api/notifications/mark-all-read', {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function(response) {
                // Run this step if the response ok is missing or false.
                if (!response.ok) throw new Error('Server returned ' + response.status);
                return response.json();
            })
            .then(function(data) {
                // Run this step if the success is set.
                if (data.success) {
                    // Process each n.
                    allNotifications.forEach(function(n) { if (!n.requires_acknowledgement) n.is_read = true; });
                    renderNotifications();
                    updateBadgeCount();
                    showSuccess('All notifications marked as read!');
                } else {
                    showError(data.message || 'Failed to mark all as read.');
                }
            })
            .catch(function(error) {
                console.error('Mark all read error:', error);
                showError('Unable to mark all notifications as read. Please try again.');
            });
        }

        // Success notification
        function showSuccess(message) {
            var notif = document.getElementById('successNotification');
            var msgSpan = document.getElementById('successMessage');
            // Run this step if the msg span is set.
            if (msgSpan) {
                msgSpan.textContent = message || 'Action completed successfully!';
            }
            notif.style.display = 'block';
            // Run this step if the success timeout is set.
            if (window.successTimeout) clearTimeout(window.successTimeout);
            // Run this action after the delay.
            window.successTimeout = setTimeout(function() {
                closeSuccess();
            }, 5000);
        }

        // Close success.
        function closeSuccess() {
            document.getElementById('successNotification').style.display = 'none';
            // Run this step if the success timeout is set.
            if (window.successTimeout) {
                clearTimeout(window.successTimeout);
                window.successTimeout = null;
            }
        }

        // Init
        document.addEventListener('DOMContentLoaded', function() {
            loadNotifications();
        });
    </script>

    {{-- Set up the actions used by this page section. --}}
    <script data-login-epoch="{{ session('navigation_epoch', 'initial') }}" src="{{ asset('js/pfims-system-ui.js') }}?v={{ filemtime(public_path('js/pfims-system-ui.js')) }}"></script>
    @include('partials.validation-alert')
</body>
</html>
