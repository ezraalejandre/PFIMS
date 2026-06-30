<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations Notifications - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Onotifications.css') }}">
    <style>
        .error-notification { z-index: 9999 !important; }
        .success-notification { z-index: 9999 !important; }
        #confirmModal { z-index: 9999 !important; }
    </style>
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
            <span id="successMessage">Action completed successfully!</span>
            <button class="success-close" onclick="closeSuccess()">×</button>
        </div>
    </div>

    <!-- ─── CONFIRM MODAL (for Clear All) ─── -->
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
                <span>User</span>
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
                    <a href="{{ url('/olandig') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%;">
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

            <div class="notif-section" id="alertsSection">
                <div class="section-title">TODAY</div>

                <div class="notif-item" data-type="alert">
                    <div class="notif-icon orange">
                        <img src="{{ asset('images/orange.jpg') }}" alt="Alert" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <div class="notif-content">
                        <div class="notif-title">Budget Threshold Reached</div>
                        <div class="notif-desc">Northgate Tower Phase 2 has consumed 88% of its allocated budget.</div>
                    </div>
                </div>

                <div class="notif-item" data-type="alert">
                    <div class="notif-icon red">
                        <img src="{{ asset('images/red.jpg') }}" alt="Alert" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <div class="notif-content">
                        <div class="notif-title">Overdue Task</div>
                        <div class="notif-desc">Steel reinforcement inspection for Block C was due 2 days ago.</div>
                    </div>
                </div>

                <div class="notif-item" data-type="alert">
                    <div class="notif-icon green">
                        <img src="{{ asset('images/green.jpg') }}" alt="Alert" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <div class="notif-content">
                        <div class="notif-title">Milestone Completed</div>
                        <div class="notif-desc">Foundation works for Harbor View Residences signed off by QA.</div>
                    </div>
                </div>

                <div class="notif-item" data-type="alert">
                    <div class="notif-icon blue">
                        <img src="{{ asset('images/blue.jpg') }}" alt="Alert" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <div class="notif-content">
                        <div class="notif-title">New Comment</div>
                        <div class="notif-desc">Ringo Santos left a note on Project #EVC-08t: 'Rebar delivery rescheduled to Friday.'</div>
                    </div>
                </div>
            </div>

            <div class="notif-section" id="systemSection">
                <div class="section-title">THIS WEEK</div>

                <div class="notif-item" data-type="system">
                    <div class="notif-icon wrench">
                        <img src="{{ asset('images/wrench.jpg') }}" alt="System" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <div class="notif-content">
                        <div class="notif-title">Equipment Maintenance Due</div>
                        <div class="notif-desc">Tower crane TC-04 is scheduled for its 500-hour service check.</div>
                    </div>
                </div>

                <div class="notif-item" data-type="system">
                    <div class="notif-icon wrench">
                        <img src="{{ asset('images/wrench.jpg') }}" alt="System" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <div class="notif-content">
                        <div class="notif-title">System Update Applied</div>
                        <div class="notif-desc">EVC-DCS was updated to v2.4.1. See release notes.</div>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <script>
        // ─── HIDE NOTIFICATION BADGE ───
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
        }

        // ─── ERROR NOTIFICATION ───
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

        // ─── CONFIRM MODAL ───
        var confirmCallback = null;

        function openClearAllModal() {
            document.getElementById('confirmMessage').textContent = 'Are you sure you want to clear all notifications?';
            confirmCallback = function() {
                var list = document.getElementById('notifList');
                list.innerHTML = '<div style="text-align: center; padding: 40px; color: #888; font-size: 1rem;">No notifications to display.</div>';
                var badge = document.getElementById('notifBadge');
                badge.textContent = '0';
                badge.style.display = 'none';
                showSuccess('All notifications cleared successfully!');
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

            var alertsSection = document.getElementById('alertsSection');
            var systemSection = document.getElementById('systemSection');

            if (type === 'all') {
                alertsSection.style.display = 'block';
                systemSection.style.display = 'block';
            } else if (type === 'alerts') {
                alertsSection.style.display = 'block';
                systemSection.style.display = 'none';
            } else if (type === 'system') {
                alertsSection.style.display = 'none';
                systemSection.style.display = 'block';
            }
        }

        // ─── MARK ALL READ ───
        function markAllRead() {
            var items = document.querySelectorAll('.notif-item');
            items.forEach(function(item) {
                item.style.opacity = '0.6';
                item.style.background = '#f9f9f9';
            });
            var badge = document.getElementById('notifBadge');
            badge.textContent = '0';
            badge.style.display = 'none';
            showSuccess('All notifications marked as read!');
        }

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