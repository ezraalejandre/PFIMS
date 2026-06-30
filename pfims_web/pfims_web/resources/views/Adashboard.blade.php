<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Dashboard - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Adashboard.css') }}">
    <style>
        .error-notification { z-index: 9999 !important; }
        .success-notification { z-index: 9999 !important; }
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
            <a href="{{ url('/anotifications') }}" onclick="hideBadge(event)" style="position: relative;">
                <img src="{{ asset('images/notif.jpg') }}" style="height: 22px; width: auto; cursor: pointer;">
                <span>Notifications</span>
                <span class="notif-badge" id="notifBadge">6</span>
            </a>
            <a href="{{ url('/aprofile') }}" style="display: flex; align-items: center; gap: 5px; color: inherit; text-decoration: none;">
                <img src="{{ asset('images/user.jpg') }}" alt="User" style="height: 30px; width: 30px; cursor: pointer; border-radius: 50%; object-fit: cover;">
                <span>User</span>
            </a>
        </div>
    </header>

    <!-- ─── SIDEBAR ─── -->
    <aside class="sidebar">
        <nav>
            <ul>
                <li class="active"><a href="{{ url('/adashboard') }}">DASHBOARD</a></li>
                <li><a href="{{ url('/afinance') }}">FINANCE</a></li>
                <li><a href="{{ url('/areports') }}">REPORTS</a></li>
            </ul>
        </nav>
        <div class="bottom-nav">
            <ul>
                <li>
                    <a href="{{ url('/asettings') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%;">
                        <img src="{{ asset('images/settings.jpg') }}" alt="Settings" class="nav-icon">
                        Settings
                    </a>
                </li>
                <li class="logout">
                    <a href="{{ url('/alanding') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%;">
                        <img src="{{ asset('images/logout.jpg') }}" alt="Log Out" class="nav-icon">
                        Log out
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- ─── MAIN CONTENT ─── -->
    <main class="main-content">

        <!-- Page Title -->
        <div class="page-header">
            <h1>DASHBOARD <small>financial overview</small></h1>
        </div>

        <!-- Stats Cards (Financial only) -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Budget</div>
                <div class="stat-value">₱67,000,000</div>
                <div class="stat-sub">₱2.1M remaining</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Net Variance</div>
                <div class="stat-value" style="color: #d32f2f;">-₱440</div>
                <div class="stat-sub">vs. planned budget</div>
            </div>
        </div>

        <!-- ─── BUDGET ALLOCATION VS SPENDING (LINE CHART) ─── -->
        <div class="charts-row">
            <div class="chart-box" style="grid-column: 1 / -1; max-width: 800px; margin: 0 auto;">
                <h3>BUDGET ALLOCATION VS SPENDING</h3>
                <div class="line-chart">
                    <svg viewBox="0 0 500 180" preserveAspectRatio="xMidYMid meet">
                        <line x1="40" y1="20" x2="480" y2="20" class="grid-line" />
                        <line x1="40" y1="60" x2="480" y2="60" class="grid-line" />
                        <line x1="40" y1="100" x2="480" y2="100" class="grid-line" />
                        <line x1="40" y1="140" x2="480" y2="140" class="grid-line" />
                        <text x="30" y="20" class="y-label">500</text>
                        <text x="30" y="60" class="y-label">400</text>
                        <text x="30" y="100" class="y-label">300</text>
                        <text x="30" y="140" class="y-label">200</text>
                        <text x="30" y="170" class="y-label">100</text>
                        <text x="30" y="175" class="y-label">0</text>
                        <polygon class="area-path" points="40,40 128,33 216,30 304,26 392,23 480,20 480,170 40,170" />
                        <polyline class="line-path" points="40,40 128,33 216,30 304,26 392,23 480,20" />
                        <circle cx="40" cy="40" r="5" class="dot" />
                        <text x="40" y="30" class="dot-label">430</text>
                        <text x="40" y="175" class="x-label">Jan</text>
                        <circle cx="128" cy="33" r="5" class="dot" />
                        <text x="128" y="23" class="dot-label">450</text>
                        <text x="128" y="175" class="x-label">Feb</text>
                        <circle cx="216" cy="30" r="5" class="dot" />
                        <text x="216" y="20" class="dot-label">460</text>
                        <text x="216" y="175" class="x-label">Mar</text>
                        <circle cx="304" cy="26" r="5" class="dot" />
                        <text x="304" y="16" class="dot-label">470</text>
                        <text x="304" y="175" class="x-label">Apr</text>
                        <circle cx="392" cy="23" r="5" class="dot" />
                        <text x="392" y="13" class="dot-label">480</text>
                        <text x="392" y="175" class="x-label">May</text>
                        <circle cx="480" cy="20" r="5" class="dot" />
                        <text x="480" y="10" class="dot-label">490</text>
                        <text x="480" y="175" class="x-label">Jun</text>
                    </svg>
                </div>
            </div>
        </div>

    </main>

    <script>
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