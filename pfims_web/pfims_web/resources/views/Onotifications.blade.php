<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Operations Notifications - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Onotifications.css') }}">
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
    </style>
</head>
<body>

    <!-- ─── ERROR NOTIFICATION ─── -->
    <div id="errorNotification" class="error-notification" style="display: none;">
        <div class="error-content">
            <span class="error-icon">⚠</span>
            <span id="errorMessage">An error occurred. Please try again.</span>
            <button class="error-close" onclick="closeError()">×</button>
        </div>
    </div>

    <!-- ─── SUCCESS NOTIFICATION ─── -->
    <div id="successNotification" class="success-notification" style="display: none;">
        <div class="success-content">
            <span class="success-icon">●</span>
            <span id="successMessage">Action completed successfully!</span>
            <button class="success-close" onclick="closeSuccess()">×</button>
        </div>
    </div>

    <!-- ─── CONFIRM MODAL ─── -->
    <div id="confirmModal" class="modal-overlay" style="display: none; z-index: 9999;">
        <div class="modal-container" style="width: 400px; max-width: 95%;">
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

    <!-- ─── FULL-WIDTH HEADER ─── -->
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
                <span class="notif-badge" id="notifBadge">0</span>
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
                <li><a href="{{ url('/odashboard') }}">DASHBOARD</a></li>
                <li><a href="{{ url('/oprojects') }}">PROJECTS</a></li>
                <li><a href="{{ url('/oinventory') }}">INVENTORY</a></li>
                <li><a href="{{ url('/osuppliers') }}">SUPPLIERS</a></li>
                <li><a href="{{ url('/oreports') }}">REPORTS</a></li>
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

        // ─── NOTIFICATION FUNCTIONS ──────────────────────────────────
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
        }

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

        // ─── CONFIRM MODAL ───
        var confirmCallback = null;

        function openClearAllModal() {
            document.getElementById('confirmMessage').textContent = 'Are you sure you want to clear all notifications?';
            confirmCallback = function() {
                fetch('/api/notifications/all', {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        loadNotifications();
                        showSuccess('All notifications cleared successfully!');
                    } else {
                        showError('Failed to clear notifications.');
                    }
                })
                .catch(function(error) {
                    showError('Failed to clear notifications.');
                });
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
                allNotifications = data.notifications || [];
                updateBadgeCount();
                renderNotifications();
            })
            .catch(function(error) {
                console.error('Error loading notifications:', error);
                list.innerHTML = '<div style="text-align: center; padding: 40px; color: #d32f2f;">Failed to load notifications. Please refresh.</div>';
            });
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
                filtered = filtered.filter(function(n) { return n.filter === 'alerts' || n.kind === 'alert'; });
            } else if (currentTab === 'system') {
                filtered = filtered.filter(function(n) { return n.filter === 'system' || n.kind === 'system'; });
            }

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
                var timeStr = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
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

                // Determine icon based on notification kind
                var iconClass = 'blue';
                var iconText = '📌';
                if (notification.kind === 'alert' || notification.filter === 'alerts') {
                    if (notification.title.toLowerCase().includes('delayed') || notification.title.toLowerCase().includes('risk')) {
                        iconClass = 'red';
                        iconText = '⚠️';
                    } else if (notification.title.toLowerCase().includes('budget')) {
                        iconClass = 'orange';
                        iconText = '💰';
                    } else {
                        iconClass = 'orange';
                        iconText = '🔔';
                    }
                } else if (notification.kind === 'system' || notification.filter === 'system') {
                    if (notification.title.includes('Completed')) {
                        iconClass = 'green';
                        iconText = '✅';
                    } else if (notification.title.includes('Update')) {
                        iconClass = 'wrench';
                        iconText = '🔧';
                    } else {
                        iconClass = 'blue';
                        iconText = 'ℹ️';
                    }
                }

                var readClass = notification.is_read ? 'read' : 'unread';

                html += `
                    <div class="notif-item ${readClass}" data-id="${notification.notification_id}" onclick="markAsRead(this)">
                        <div class="notif-icon ${iconClass}">${iconText}</div>
                        <div class="notif-content">
                            <div class="notif-title">${notification.title}</div>
                            <div class="notif-desc">${notification.message}</div>
                            <div class="notif-time">${isToday ? 'Today' : isYesterday ? 'Yesterday' : dateStr} at ${timeStr}</div>
                        </div>
                        ${!notification.is_read ? '<div style="width: 8px; height: 8px; background: #1a237e; border-radius: 50%; flex-shrink: 0;"></div>' : ''}
                    </div>
                `;
            });

            html += '</div>';
            list.innerHTML = html;
        }

        // ─── MARK AS READ ───
        function markAsRead(element) {
            var id = element.dataset.id;
            if (!id) return;

            // Don't mark if already read
            if (element.classList.contains('read')) return;

            fetch('/api/notifications/' + id + '/read', {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    element.classList.remove('unread');
                    element.classList.add('read');
                    var badge = element.querySelector('.notif-icon + div + div');
                    if (badge) badge.remove();
                    // Update local data
                    var notif = allNotifications.find(function(n) { return n.notification_id == id; });
                    if (notif) notif.is_read = true;
                    updateBadgeCount();
                }
            })
            .catch(function(error) {
                console.error('Error marking notification as read:', error);
            });
        }

        // ─── MARK ALL READ ───
        function markAllRead() {
            fetch('/api/notifications/all/read', {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    allNotifications.forEach(function(n) { n.is_read = true; });
                    renderNotifications();
                    updateBadgeCount();
                    showSuccess('All notifications marked as read!');
                } else {
                    showError('Failed to mark all as read.');
                }
            })
            .catch(function(error) {
                showError('Failed to mark all as read.');
            });
        }

        document.addEventListener('click', function(e) {
            if (document.getElementById('errorNotification').style.display === 'block') {
                if (!e.target.closest('.error-notification')) { closeError(); }
            }
            if (document.getElementById('successNotification').style.display === 'block') {
                if (!e.target.closest('.success-notification')) { closeSuccess(); }
            }
        });

        // ─── INIT ───
        document.addEventListener('DOMContentLoaded', function() {
            loadNotifications();
            // Auto-refresh every 30 seconds
            setInterval(function() {
                loadNotifications();
            }, 30000);
        });
    </script>

</body>
</html>