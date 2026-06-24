<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
</head>
<body>

    @include('partials.header')

    <!-- ─── MAIN CONTENT ─── -->
    <main class="main-content">

        <!-- Page Title -->
        <div class="page-header">
            <h1>DASHBOARD <small>construction operation overview</small></h1>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Active Projects</div>
                <div class="stat-value">24</div>
                <div class="stat-sub">6 completed this month</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Budget</div>
                <div class="stat-value">$18.6M</div>
                <div class="stat-sub">$2.1M remaining</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Equipment Units</div>
                <div class="stat-value">156</div>
                <div class="stat-sub">12 under maintenance</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Workforce</div>
                <div class="stat-value">342</div>
                <div class="stat-sub">28 new hires this month</div>
            </div>
        </div>

        <!-- ─── CHARTS ROW ─── -->
        <div class="charts-row">

            <!-- 1. PROJECT COMPLETION TREND (BAR CHART - BLACK) -->
            <div class="chart-box">
                <h3>PROJECT COMPLETION TREND</h3>
                <div class="bar-chart">
                    <div class="bar-group"><div class="bar" style="height:35px;"></div><span class="bar-label">Jan</span></div>
                    <div class="bar-group"><div class="bar" style="height:60px;"></div><span class="bar-label">Feb</span></div>
                    <div class="bar-group"><div class="bar" style="height:50px;"></div><span class="bar-label">Mar</span></div>
                    <div class="bar-group"><div class="bar" style="height:80px;"></div><span class="bar-label">Apr</span></div>
                    <div class="bar-group"><div class="bar" style="height:70px;"></div><span class="bar-label">May</span></div>
                    <div class="bar-group"><div class="bar" style="height:90px;"></div><span class="bar-label">Jun</span></div>
                </div>
            </div>

            <!-- 2. BUDGET ALLOCATION VS SPENDING (LINE GRAPH - ORANGE) -->
            <div class="chart-box">
                <h3>BUDGET ALLOCATION VS SPENDING</h3>
                <div class="line-chart">
                    <svg viewBox="0 0 500 180" preserveAspectRatio="xMidYMid meet">
                        <!-- Y-Axis Grid Lines -->
                        <line x1="40" y1="20" x2="480" y2="20" class="grid-line" />
                        <line x1="40" y1="60" x2="480" y2="60" class="grid-line" />
                        <line x1="40" y1="100" x2="480" y2="100" class="grid-line" />
                        <line x1="40" y1="140" x2="480" y2="140" class="grid-line" />

                        <!-- Y-Axis Labels -->
                        <text x="30" y="20" class="y-label">500</text>
                        <text x="30" y="60" class="y-label">400</text>
                        <text x="30" y="100" class="y-label">300</text>
                        <text x="30" y="140" class="y-label">200</text>
                        <text x="30" y="170" class="y-label">100</text>
                        <text x="30" y="175" class="y-label">0</text>

                        <!-- Area under the line -->
                        <polygon class="area-path"
                            points="40,40 128,33 216,30 304,26 392,23 480,20 480,170 40,170"
                        />

                        <!-- The Line Path -->
                        <polyline class="line-path"
                            points="40,40 128,33 216,30 304,26 392,23 480,20"
                        />

                        <!-- Dots with Values -->
                        <!-- Jan: 430 -->
                        <circle cx="40" cy="40" r="5" class="dot" />
                        <text x="40" y="30" class="dot-label">430</text>
                        <text x="40" y="175" class="x-label">Jan</text>

                        <!-- Feb: 450 -->
                        <circle cx="128" cy="33" r="5" class="dot" />
                        <text x="128" y="23" class="dot-label">450</text>
                        <text x="128" y="175" class="x-label">Feb</text>

                        <!-- Mar: 460 -->
                        <circle cx="216" cy="30" r="5" class="dot" />
                        <text x="216" y="20" class="dot-label">460</text>
                        <text x="216" y="175" class="x-label">Mar</text>

                        <!-- Apr: 470 -->
                        <circle cx="304" cy="26" r="5" class="dot" />
                        <text x="304" y="16" class="dot-label">470</text>
                        <text x="304" y="175" class="x-label">Apr</text>

                        <!-- May: 480 -->
                        <circle cx="392" cy="23" r="5" class="dot" />
                        <text x="392" y="13" class="dot-label">480</text>
                        <text x="392" y="175" class="x-label">May</text>

                        <!-- Jun: 490 -->
                        <circle cx="480" cy="20" r="5" class="dot" />
                        <text x="480" y="10" class="dot-label">490</text>
                        <text x="480" y="175" class="x-label">Jun</text>
                    </svg>
                </div>
            </div>

        </div>
        <!-- ─── END CHARTS ROW ─── -->

        <!-- Projects List (with hover effects) -->
        <div class="projects-section">
            <h2>ACTIVE PROJECTS</h2>
            <div class="projects-list">
                <!-- Project 1 -->
                <div class="project-item">
                    <img src="{{ asset('images/building1.jpg') }}" alt="Riverside Commercial Complex">
                    <div class="info">
                        <h4>Riverside Commercial Complex</h4>
                        <div class="budget">Budget: $2.4M</div>
                    </div>
                    <div class="progress-wrapper">
                        <div class="progress-bar"><div class="fill" style="width:78%;"></div></div>
                        <div class="progress-label"><span>78%</span><span>Complete</span></div>
                    </div>
                </div>

                <!-- Project 2 -->
                <div class="project-item">
                    <img src="{{ asset('images/building2.jpg') }}" alt="Downtown Office Tower">
                    <div class="info">
                        <h4>Downtown Office Tower</h4>
                        <div class="budget">Budget: $5.8M</div>
                    </div>
                    <div class="progress-wrapper">
                        <div class="progress-bar"><div class="fill" style="width:45%;"></div></div>
                        <div class="progress-label"><span>45%</span><span>Complete</span></div>
                    </div>
                </div>

                <!-- Project 3 -->
                <div class="project-item">
                    <img src="{{ asset('images/building3.jpg') }}" alt="Suburban Housing Development">
                    <div class="info">
                        <h4>Suburban Housing Development</h4>
                        <div class="budget">Budget: $3.2M</div>
                    </div>
                    <div class="progress-wrapper">
                        <div class="progress-bar"><div class="fill" style="width:92%;"></div></div>
                        <div class="progress-label"><span>92%</span><span>Complete</span></div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script>
        // ─── HIDE NOTIFICATION BADGE ON CLICK ───
        function hideBadge(event) {
            // Prevent the default navigation if you want to stay on page? 
            // But the user likely wants to go to notifications page and hide the badge.
            // We'll let the navigation happen and hide the badge immediately.
            var badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
            // Optionally, you can also update the badge count via AJAX later.
            // The link will still navigate to /notifications.
        }
    </script>

</body>
</html>