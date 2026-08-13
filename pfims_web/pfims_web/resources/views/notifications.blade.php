<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Notifications - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/notifications.css') }}">
    <style>
        .error-notification { z-index: 9999 !important; }
        .success-notification { z-index: 9999 !important; }
        #confirmModal { z-index: 9999 !important; }
        
        /* Notification icon colors */
        .notif-icon.green { background: #e8f5e9; color: #2e7d32; }
        .notif-icon.orange { background: #fff3e0; color: #e65100; }
        .notif-icon.red { background: #ffebee; color: #c62828; }
        .notif-icon.blue { background: #e3f2fd; color: #0d47a1; }
        .notif-icon.wrench { background: #f5f5f5; color: #616161; }
        .notif-icon.purple { background: #f3e5f5; color: #6a1b9a; }
        
        .notif-item.unread {
            background: #f0f7ff;
            border-left: 4px solid #1a237e;
        }
        
        .notif-item.read {
            opacity: 0.7;
        }
        
        .notif-item .notif-time {
            font-size: 0.7rem;
            color: #aaa;
            margin-top: 4px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #aaa;
        }
        
        .empty-state .empty-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
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
        
        .total-notif-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }
        
        .total-notif-item .total-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .total-notif-item .total-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1a2b3c;
        }
        
        .total-notif-item .total-count {
            font-size: 1.2rem;
            font-weight: 700;
            color: #c9a96e;
            background: #f8f6f2;
            padding: 2px 12px;
            border-radius: 12px;
        }
        
        .total-notif-item .total-count.red { color: #d32f2f; background: #ffebee; }
        .total-notif-item .total-count.orange { color: #e65100; background: #fff3e0; }
        .total-notif-item .total-count.green { color: #2e7d32; background: #e8f5e9; }
        .total-notif-item .total-count.blue { color: #1565c0; background: #e3f2fd; }
        
        .total-notif-item .total-message {
            font-size: 0.8rem;
            color: #888;
            margin-top: 4px;
        }
        
        .total-notif-item .total-time {
            font-size: 0.7rem;
            color: #aaa;
            margin-top: 6px;
        }
        
        .total-notif-item.card-red { border-left-color: #d32f2f; }
        .total-notif-item.card-orange { border-left-color: #e65100; }
        .total-notif-item.card-green { border-left-color: #2e7d32; }
        .total-notif-item.card-blue { border-left-color: #1565c0; }
        
        #confirmModal .modal-container {
            width: 400px;
            max-width: 95%;
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}">
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body class="notifications-page">

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
            <span id="successMessage">Action completed successfully!</span>
            <button class="success-close" onclick="closeSuccess()">×</button>
        </div>
    </div>

    <!-- ─── CONFIRM MODAL (for Clear All) ─── -->
    <div id="confirmModal" class="modal-overlay" style="display: none; z-index: 9999;">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Confirm Action</h2>
                <button class="modal-close" onclick="closeConfirmModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="confirmMessage" style="font-size: 1rem; color: #333; margin-bottom: 10px;">
                    Are you sure you want to clear all notifications?
                </p>
                <p style="font-size: 0.85rem; color: #888; margin-bottom: 20px;">
                    This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: center; gap: 12px; margin-top: 10px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                <button class="btn-cancel" onclick="closeConfirmModal()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: transparent; color: #888; transition: 0.3s;">Cancel</button>
                <button class="btn-delete" id="confirmActionBtn" onclick="confirmAction()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: #d32f2f; color: #fff; transition: 0.3s;">Yes, Clear All</button>
            </div>
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

    <!-- ─── SIDEBAR ─── -->
    <aside class="sidebar">
        <nav>
            <ul>
                <li><a href="{{ url('/dashboard') }}" style="color: inherit; text-decoration: none; display: block;">DASHBOARD</a></li>
                <li><a href="{{ url('/projects') }}" style="color: inherit; text-decoration: none; display: block;">PROJECTS</a></li>
                <li><a href="{{ url('/finance') }}" style="color: inherit; text-decoration: none; display: block;">FINANCE</a></li>
                <li><a href="{{ url('/inventory') }}" style="color: inherit; text-decoration: none; display: block;">INVENTORY</a></li>
                <li><a href="{{ url('/suppliers') }}" style="color: inherit; text-decoration: none; display: block;">SUPPLIERS</a></li>
                <li><a href="{{ url('/reports') }}" style="color: inherit; text-decoration: none; display: block;">REPORTS</a></li>
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
            <div>
                <h1>NOTIFICATIONS</h1>
                <div class="subtitle">alerts &amp; system updates</div>
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

        <!-- ─── NOTIFICATIONS LIST ─── -->
        <div id="notifList">
            <div style="text-align: center; padding: 40px; color: #888;">Loading notifications...</div>
        </div>

    </main>

    <script>
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        var allNotifications = [];
        var currentTab = 'all';
        var showOnlyTotals = false;

        // Category muting from the original file, combined with the E-version
        // acknowledgement flow. Mandatory notices are never hidden.
        var MUTABLE_TYPES = {
            project_updates: ['project_delayed', 'project_at_risk', 'project_delayed_total', 'project_at_risk_total', 'project_past_deadline_total'],
            budget_alerts: ['budget_expense_overrun_total', 'new_expense', 'new_budget', 'budget_updated'],
            inventory_alerts: ['item_low_stock', 'item_low_stock_total', 'item_out_of_stock', 'stock_in_expense']
        };
        function filterMutedNotifications(list) {
            var mutedTypes = [];
            Object.keys(MUTABLE_TYPES).forEach(function(category) {
                if (localStorage.getItem('notif_mute_' + category) === 'true') mutedTypes = mutedTypes.concat(MUTABLE_TYPES[category]);
            });
            return list.filter(function(n) { return n.requires_acknowledgement || mutedTypes.indexOf(n.type) === -1; });
        }

        // ─── HIDE NOTIFICATION BADGE ───
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
        }

        // ─── CONFIRM MODAL ───
        var confirmCallback = null;

        function openClearAllModal() {
            document.getElementById('confirmMessage').textContent = 'Are you sure you want to clear all notifications?';
            confirmCallback = function() {
                // Show only total notifications after clearing
                showOnlyTotals = true;
                renderNotifications();
                updateBadgeCount();
                showSuccess('All notifications cleared! Showing summary only.');
            };
            document.getElementById('confirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').style.display = 'none';
            document.body.style.overflow = '';
            confirmCallback = null;
        }

        function confirmAction() {
            if (typeof confirmCallback === 'function') {
                confirmCallback();
            }
            closeConfirmModal();
        }

        document.getElementById('confirmModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeConfirmModal();
            }
        });

        // ─── TAB SWITCHING ───
        function switchTab(el, type) {
            var tabs = document.querySelectorAll('.notif-tabs .tab');
            tabs.forEach(function(tab) {
                tab.classList.remove('active');
            });
            el.classList.add('active');
            currentTab = type;
            renderNotifications();
        }

        // ─── GET NOTIFICATION ICON BASED ON KIND ───
        function getNotificationIcon(kind) {
            var iconMap = {
                'warning': { class: 'orange', text: '⚠️' },
                'overdue': { class: 'red', text: '⏰' },
                'success': { class: 'green', text: '✅' },
                'info': { class: 'blue', text: 'ℹ️' },
                'maintenance': { class: 'wrench', text: '🔧' },
                'system_update': { class: 'purple', text: '🔄' }
            };
            return iconMap[kind] || { class: 'blue', text: '📌' };
        }

        // ─── GET TOTAL NOTIFICATIONS BY TYPE ───
        function getTotalNotifications() {
            var totals = [];
            var types = {};

            allNotifications.forEach(function(n) {
                if (n.type && n.type.endsWith('_total')) {
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

        // ─── LOAD NOTIFICATIONS ───
        function loadNotifications() {
            var list = document.getElementById('notifList');
            list.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">Loading notifications...</div>';

            fetch('/api/notifications', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function(response) {
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

        // ─── LOAD MOCK NOTIFICATIONS ───
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

        // ─── UPDATE BADGE COUNT ───
        function updateBadgeCount() {
            var unread = allNotifications.filter(function(n) { return !n.is_read; }).length;
            var badge = document.getElementById('notifBadge');
            if (unread > 0) {
                badge.textContent = unread;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }

        // ─── RENDER NOTIFICATIONS ───
        function renderNotifications() {
            var list = document.getElementById('notifList');
            var filtered = allNotifications;

            // Filter by tab
            if (currentTab === 'alerts') {
                filtered = filtered.filter(function(n) { 
                    return n.filter === 'alerts' || n.kind === 'alert' || n.kind === 'warning' || n.kind === 'overdue'; 
                });
            } else if (currentTab === 'system') {
                filtered = filtered.filter(function(n) { 
                    return n.filter === 'system' || n.kind === 'system' || n.kind === 'system_update' || n.kind === 'maintenance'; 
                });
            }

            // If showing only totals, display grouped notifications
            if (showOnlyTotals) {
                var totals = getTotalNotifications();
                
                // Filter totals by tab
                if (currentTab === 'alerts') {
                    totals = totals.filter(function(n) { 
                        return n.filter === 'alerts' || n.kind === 'alert' || n.kind === 'warning' || n.kind === 'overdue'; 
                    });
                } else if (currentTab === 'system') {
                    totals = totals.filter(function(n) { 
                        return n.filter === 'system' || n.kind === 'system' || n.kind === 'system_update' || n.kind === 'maintenance'; 
                    });
                }

                if (totals.length === 0) {
                    list.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-icon">🔔</div>
                            <div style="font-size: 1.1rem; color: #666;">No notifications</div>
                            <div style="font-size: 0.85rem; color: #aaa; margin-top: 5px;">You're all caught up!</div>
                        </div>
                    `;
                    return;
                }

                var html = '';
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
                                <span class="total-title">${icon.text} ${total.title}</span>
                                <span class="total-count ${countClass}">${total.count}</span>
                            </div>
                            <div class="total-message">${total.message}</div>
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

            if (filtered.length === 0) {
                list.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">🔔</div>
                        <div style="font-size: 1.1rem; color: #666;">No notifications</div>
                        <div style="font-size: 0.85rem; color: #aaa; margin-top: 5px;">You're all caught up!</div>
                    </div>
                `;
                return;
            }

            var html = '';
            var currentDate = '';

            filtered.forEach(function(notification) {
                var date = new Date(notification.created_at);
                var dateStr = date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
                var isToday = new Date().toDateString() === date.toDateString();
                var isYesterday = new Date(Date.now() - 86400000).toDateString() === date.toDateString();

                var sectionTitle = isToday ? 'TODAY' : (isYesterday ? 'YESTERDAY' : dateStr);

                if (sectionTitle !== currentDate) {
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
                            <div class="notif-title">${notification.title}</div>
                            <div class="notif-desc">${notification.message}</div>
                            <div class="notif-time">${isToday ? 'Today' : isYesterday ? 'Yesterday' : dateStr} at ${timeStr}</div>
                            ${notification.requires_acknowledgement ? '<button type="button" style="margin-top:10px; padding:8px 14px; border:0; border-radius:8px; background:#c9a96e; color:#fff; font-weight:600; cursor:pointer;" onclick="event.stopPropagation(); window.location.href=\'' + notification.action_url + '\'">Change Password</button>' : ''}
                        </div>
                        ${!notification.is_read ? '<div style="width: 8px; height: 8px; background: #1a237e; border-radius: 50%; flex-shrink: 0; margin-left: 10px;"></div>' : ''}
                    </div>
                `;
            });

            html += '</div>';
            list.innerHTML = html;
        }

        // ─── EXPAND TOTAL TO SHOW INDIVIDUAL NOTIFICATIONS ───
        function expandTotal(type) {
            showOnlyTotals = false;
            // Reload to get individual notifications back
            loadNotifications();
        }

        // ─── MARK AS READ ───
        function markAsRead(element) {
            var id = element.dataset.id;
            if (!id) return;

            if (element.classList.contains('read')) return;

            fetch('/api/notifications/' + id + '/read', {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Failed to mark as read');
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    if (data.removed) {
                        allNotifications = allNotifications.filter(function(n) { return n.notification_id != id; });
                        renderNotifications();
                        updateBadgeCount();
                        showSuccess('Reminder acknowledged.');
                        return;
                    }
                    element.classList.remove('unread');
                    element.classList.add('read');
                    var dot = element.querySelector('.notif-icon + .notif-content + div');
                    if (dot) dot.remove();
                    var notif = allNotifications.find(function(n) { return n.notification_id == id; });
                    if (notif) notif.is_read = true;
                    updateBadgeCount();
                }
            })
            .catch(function(error) {
                console.error('Error marking notification as read:', error);
                element.classList.remove('unread');
                element.classList.add('read');
                var dot = element.querySelector('.notif-icon + .notif-content + div');
                if (dot) dot.remove();
                var notif = allNotifications.find(function(n) { return n.notification_id == id; });
                if (notif) notif.is_read = true;
                updateBadgeCount();
            });
        }

        // ─── MARK ALL READ ───
        function markAllRead() {
            var unreadIds = allNotifications.filter(function(n) { return !n.is_read && !n.requires_acknowledgement; }).map(function(n) { return n.notification_id; });
            
            if (unreadIds.length === 0) {
                showSuccess('All notifications are already read!');
                return;
            }

            fetch('/api/notifications/all/read', {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Server returned ' + response.status);
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
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
                allNotifications.forEach(function(n) { if (!n.requires_acknowledgement) n.is_read = true; });
                renderNotifications();
                updateBadgeCount();
                showSuccess('All notifications marked as read!');
            });
        }

        // ─── SUCCESS NOTIFICATION ───
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

        // ─── INIT ───
        document.addEventListener('DOMContentLoaded', function() {
            loadNotifications();
            setInterval(function() {
                loadNotifications();
            }, 60000);
        });
    </script>

</body>
</html>
