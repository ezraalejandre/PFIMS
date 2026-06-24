<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/notifications.css') }}">
</head>
<body>

    @include('partials.header')

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
                <button class="btn-clear-all" onclick="clearAll()">✕ Clear all</button>
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

            <!-- TODAY SECTION -->
            <div class="notif-section" id="alertsSection">
                <div class="section-title">TODAY</div>

                <!-- Budget Threshold Reached - Orange -->
                <div class="notif-item" data-type="alert">
                    <div class="notif-icon orange">
                        <img src="{{ asset('images/orange.jpg') }}" alt="Alert" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <div class="notif-content">
                        <div class="notif-title">Budget Threshold Reached</div>
                        <div class="notif-desc">Northgate Tower Phase 2 has consumed 88% of its allocated budget.</div>
                    </div>
                </div>

                <!-- Overdue Task - Red -->
                <div class="notif-item" data-type="alert">
                    <div class="notif-icon red">
                        <img src="{{ asset('images/red.jpg') }}" alt="Alert" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <div class="notif-content">
                        <div class="notif-title">Overdue Task</div>
                        <div class="notif-desc">Steel reinforcement inspection for Block C was due 2 days ago.</div>
                    </div>
                </div>

                <!-- Milestone Completed - Green -->
                <div class="notif-item" data-type="alert">
                    <div class="notif-icon green">
                        <img src="{{ asset('images/green.jpg') }}" alt="Alert" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <div class="notif-content">
                        <div class="notif-title">Milestone Completed</div>
                        <div class="notif-desc">Foundation works for Harbor View Residences signed off by QA.</div>
                    </div>
                </div>

                <!-- New Comment - Blue -->
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

            <!-- THIS WEEK SECTION -->
            <div class="notif-section" id="systemSection">
                <div class="section-title">THIS WEEK</div>

                <!-- Equipment Maintenance Due - Wrench -->
                <div class="notif-item" data-type="system">
                    <div class="notif-icon wrench">
                        <img src="{{ asset('images/wrench.jpg') }}" alt="System" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <div class="notif-content">
                        <div class="notif-title">Equipment Maintenance Due</div>
                        <div class="notif-desc">Tower crane TC-04 is scheduled for its 500-hour service check.</div>
                    </div>
                </div>

                <!-- System Update Applied - Wrench -->
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

            console.log('Switched to: ' + type);
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
            alert('All notifications marked as read!');
        }

        // ─── CLEAR ALL ───
        function clearAll() {
            if (confirm('Are you sure you want to clear all notifications?')) {
                var list = document.getElementById('notifList');
                list.innerHTML = '<div style="text-align: center; padding: 40px; color: #888; font-size: 1rem;">No notifications to display.</div>';
                var badge = document.getElementById('notifBadge');
                badge.textContent = '0';
                badge.style.display = 'none';
                alert('All notifications cleared!');
            }
        }
    </script>

</body>
</html>