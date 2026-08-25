@php($fragment = $fragment ?? false)
@unless($fragment)
<!DOCTYPE html>
<html lang="en" class="{{ request()->boolean('embedded') ? 'embedded-ml-document' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ML Dashboard - Test Preview</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            padding: 20px;
            color: #333;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #1a237e, #0d47a1);
            color: white;
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .header small {
            font-weight: 300;
            opacity: 0.8;
            font-size: 14px;
            display: block;
            margin-top: 4px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #ffc107;
            color: #1a237e;
        }

        .btn-primary:hover {
            background: #ffb300;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
        }

        .btn-success {
            background: #4caf50;
            color: white;
        }

        .btn-success:hover {
            background: #43a047;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-danger:hover {
            background: #d32f2f;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(244, 67, 54, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.1);
            border-color: white;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }

        .stat-label {
            font-size: 13px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin: 8px 0 4px;
            color: #1a237e;
        }

        .stat-sub {
            font-size: 13px;
            color: #666;
        }

        .stat-card .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 6px;
        }

        .status-badge.success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.warning {
            background: #fff3e0;
            color: #e65100;
        }

        .status-badge.danger {
            background: #ffebee;
            color: #c62828;
        }

        .status-badge.info {
            background: #e3f2fd;
            color: #0d47a1;
        }

        /* Main Grid */
        .main-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 18px;
            margin-bottom: 18px;
        }

        @media (max-width: 1024px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 25px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f2f5;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a237e;
        }

        .card-title small {
            font-weight: 400;
            font-size: 13px;
            color: #888;
            margin-left: 8px;
        }

        /* Prediction Form */
        .prediction-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
        }

        .form-group input, 
        .form-group select {
            padding: 10px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            background: #fafafa;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #1a237e;
            background: white;
        }

        .form-group input:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }

        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 12px;
            margin-top: 5px;
        }

        .project-selection {
            display: grid;
            gap: 14px;
        }

        .project-selection select {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #d7dde6;
            border-radius: 7px;
            background: #fff;
            color: #172033;
            font: inherit;
        }

        .project-selection-note {
            margin: 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        .project-snapshot-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .project-snapshot-grid[hidden] {
            display: none;
        }

        .snapshot-item {
            min-width: 0;
            padding: 11px 13px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
        }

        .snapshot-item span,
        .snapshot-item strong {
            display: block;
        }

        .snapshot-item span {
            margin-bottom: 4px;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .03em;
            text-transform: uppercase;
        }

        .snapshot-item strong {
            color: #172033;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        /* Prediction Result */
        .prediction-result {
            margin-top: 20px;
            padding: 20px;
            border-radius: 10px;
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .prediction-result.show {
            display: block;
        }

        .prediction-result.success {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border: 2px solid #66bb6a;
        }

        .prediction-result.warning {
            background: linear-gradient(135deg, #fff3e0, #ffe0b2);
            border: 2px solid #ffa726;
        }

        .prediction-result.danger {
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            border: 2px solid #ef5350;
        }

        .prediction-result h4 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #333;
        }

        .prediction-result .result-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }

        .prediction-result .result-row:last-child {
            border-bottom: none;
        }

        .prediction-result .result-label {
            font-weight: 500;
            color: #555;
        }

        .prediction-result .result-value {
            font-weight: 600;
            color: #1a237e;
        }

        .prediction-result .result-value.positive {
            color: #2e7d32;
        }

        .prediction-result .result-value.negative {
            color: #c62828;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Model Status */
        .model-status-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .metric-section {
            margin-top: 18px;
        }

        .metric-section:first-child {
            margin-top: 0;
        }

        .metric-section-title {
            margin: 0 0 10px;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .model-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .metric-item.metric-detail {
            min-height: 110px;
            align-items: flex-start;
            flex-direction: column;
            justify-content: flex-start;
            gap: 8px;
        }

        .metric-item.metric-detail .metric-value {
            font-size: 13px;
            font-weight: 400;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }

        .metric-item.metric-interpretation {
            grid-column: 1 / -1;
            background: #f0f4ff;
            border-left: 4px solid #1a2b3c;
        }

        .metric-item {
            background: #f8f9fa;
            padding: 12px 16px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .metric-item .metric-label {
            font-size: 13px;
            color: #666;
        }

        .metric-item .metric-value {
            font-weight: 700;
            color: #1a237e;
            font-size: 16px;
        }

        .metric-item .metric-value.good {
            color: #2e7d32;
        }

        .metric-item .metric-value.average {
            color: #f57c00;
        }

        .metric-item .metric-value.poor {
            color: #c62828;
        }

        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 3px solid rgba(26, 35, 126, 0.1);
            border-radius: 50%;
            border-top-color: #1a237e;
            animation: spin 0.8s ease infinite;
            vertical-align: middle;
            margin-right: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Notifications */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 10px;
            color: white;
            font-weight: 500;
            z-index: 9999;
            animation: slideIn 0.4s ease;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .notification.success {
            background: linear-gradient(135deg, #43a047, #2e7d32);
        }

        .notification.error {
            background: linear-gradient(135deg, #ef5350, #c62828);
        }

        .notification.info {
            background: linear-gradient(135deg, #1e88e5, #0d47a1);
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .notification-close {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            margin-left: 15px;
            opacity: 0.7;
        }

        .notification-close:hover {
            opacity: 1;
        }

        .ml-confirm-overlay {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, .58);
            backdrop-filter: blur(3px);
        }

        .ml-confirm-overlay[hidden] {
            display: none;
        }

        .ml-confirm-dialog {
            width: min(440px, 100%);
            padding: 24px;
            border: 1px solid #dfe5ed;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .24);
        }

        .ml-confirm-dialog h2 {
            margin: 0 0 9px;
            color: #172033;
            font-size: 19px;
        }

        .ml-confirm-dialog p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.55;
        }

        .ml-confirm-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 22px;
        }

        html[data-theme="dark"] .ml-confirm-dialog {
            border-color: #344258;
            background: #172033;
        }

        html[data-theme="dark"] .ml-confirm-dialog h2 {
            color: #edf2f7;
        }

        html[data-theme="dark"] .ml-confirm-dialog p {
            color: #b7c3d4;
        }

        /* Material Table */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        table th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e0e0e0;
        }

        table td {
            padding: 10px 15px;
            border-bottom: 1px solid #f0f2f5;
        }

        table tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-warning {
            background: #fff3e0;
            color: #e65100;
        }

        .badge-danger {
            background: #ffebee;
            color: #c62828;
        }

        .badge-info {
            background: #e3f2fd;
            color: #0d47a1;
        }

        .text-center {
            text-align: center;
        }

        .mt-20 {
            margin-top: 20px;
        }

        .mb-20 {
            margin-bottom: 20px;
        }

        .hidden {
            display: none;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }

        .tab-btn {
            padding: 8px 20px;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 600;
            color: #888;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .tab-btn:hover {
            background: #f0f2f5;
        }

        .tab-btn.active {
            color: #1a237e;
            background: #e8eaf6;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .header-actions {
                flex-wrap: wrap;
                justify-content: center;
            }

            .prediction-form {
                grid-template-columns: 1fr;
            }

            .project-snapshot-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .model-status-grid {
                grid-template-columns: 1fr;
            }

            .model-detail-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .notification {
                left: 20px;
                right: 20px;
                max-width: none;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 20px;
            }

            .stat-value {
                font-size: 22px;
            }

            .project-snapshot-grid {
                grid-template-columns: 1fr;
            }
        }

        html.embedded-ml-document,
        body.embedded-ml-dashboard {
            width: 100%;
            min-width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            overflow-y: hidden;
        }

        body.embedded-ml-dashboard {
            background: transparent;
            color: #172033;
        }

        .embedded-ml-dashboard .dashboard-container {
            width: 100%;
            min-width: 100%;
            max-width: none;
            margin: 0;
            padding: 0;
        }

        .embedded-ml-dashboard :where(.header, .main-grid, .model-performance-card, .card, .table-responsive) {
            width: 100%;
            min-width: 0;
            max-width: 100%;
        }

        .embedded-ml-dashboard .main-grid > div {
            width: 100%;
            min-width: 0;
        }

        .embedded-ml-dashboard .header,
        .embedded-ml-dashboard .card,
        .embedded-ml-dashboard .stat-card {
            border: 1px solid #dfe5ed;
            border-radius: 10px;
            background: #fff;
            color: #172033;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }

        .embedded-ml-dashboard .header {
            padding: 20px 22px;
            margin-bottom: 18px;
        }

        .embedded-ml-dashboard .stats-grid,
        .embedded-ml-dashboard .main-grid {
            gap: 18px;
            margin-bottom: 18px;
        }

        .embedded-ml-dashboard .card {
            margin-bottom: 18px;
            padding: 20px;
        }

        .embedded-ml-dashboard .card-title {
            color: #172033;
            font-size: 16px;
        }

        .embedded-ml-dashboard :where(.form-group input, .form-group select) {
            border: 1px solid #d7dde6;
            border-radius: 7px;
            background: #fff;
        }

        .embedded-ml-dashboard :where(.form-group input, .form-group select):focus {
            border-color: #64748b;
            box-shadow: 0 0 0 3px rgba(100, 116, 139, .14);
        }

        .embedded-ml-dashboard .btn-success {
            background: #1a2b3c;
            color: #fff;
        }

        .embedded-ml-dashboard .model-performance-card .metric-item {
            min-width: 0;
            align-items: flex-start;
            flex-direction: column;
            justify-content: flex-start;
            gap: 7px;
        }

        .embedded-ml-dashboard .model-performance-card .metric-value {
            max-width: 100%;
            overflow-wrap: anywhere;
        }

        .embedded-ml-dashboard .header h1,
        .embedded-ml-dashboard .header small {
            color: #172033;
        }

        .embedded-ml-dashboard .header small {
            opacity: 0.68;
        }

        .embedded-ml-dashboard .btn-primary,
        .embedded-ml-dashboard .tab-btn.active {
            background: #1a2b3c;
            color: #fff;
        }

        .embedded-ml-dashboard .btn-primary:hover {
            background: #253d54;
            box-shadow: none;
        }

        .embedded-ml-dashboard .card-header {
            border-bottom-color: #e5eaf0;
        }

        .embedded-ml-dashboard .stat-card {
            border-left: 1px solid #dfe5ed;
        }

        html[data-theme="dark"] body.embedded-ml-dashboard {
            color: #edf2f7;
        }

        html[data-theme="dark"] .embedded-ml-dashboard :where(.header, .card, .stat-card) {
            border-color: #344258;
            background: #172033;
            color: #edf2f7;
        }

        html[data-theme="dark"] .embedded-ml-dashboard :where(.header h1, .header small, .card-title, .stat-value, .metric-value) {
            color: #edf2f7;
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}">
    <link rel="stylesheet" href="{{ asset('css/centralized-predictive-analytics.css') }}">
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body class="{{ request()->boolean('embedded') ? 'embedded-ml-dashboard' : '' }}">
@endunless

    <div id="predictiveAnalyticsRoot" class="predictive-analytics-root embedded-ml-dashboard" data-api-base="{{ url('/api/ml') }}">
    <div class="dashboard-container analytics-shell">
        <section class="header analytics-toolbar">
            <div class="analytics-heading">
                <span class="analytics-eyebrow">DECISION SUPPORT</span>
                <h1>Predictive Analytics</h1>
                <p>Review project cost forecasts, material demand, budget variance, and model reliability.</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline analytics-button" type="button" onclick="refreshData()">
                    Refresh data
                </button>
                @if(auth()->check() && strtolower((string) auth()->user()->role) === 'admin')
                    <button class="btn btn-primary analytics-button" type="button" onclick="openRetrainConfirmation()">
                        Retrain model
                    </button>
                @endif
            </div>
        </section>

        <!-- Main Grid -->
        <div class="main-grid">
            <!-- Left Column: Prediction -->
            <div>
                <section class="card analytics-panel prediction-workspace">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Project Cost Prediction</div>
                            <p class="analytics-panel-description">Select an eligible project to forecast its final cost from current system records.</p>
                        </div>
                        <span class="badge badge-info">Linear regression</span>
                    </div>

                    <form id="predictionForm" onsubmit="return false;">
                        <div class="project-selection">
                            <div class="form-group">
                                <label for="predictionProject">Project to predict</label>
                                <select id="predictionProject" required disabled>
                                    <option value="">Loading eligible projects…</option>
                                </select>
                            </div>
                            <p class="project-selection-note" id="predictionProjectNote">Only incomplete projects with a recorded budget, schedule, and workforce are available. Prediction inputs come directly from current project and finance records.</p>
                            <div class="project-snapshot-grid" id="predictionProjectSnapshot" hidden>
                                <div class="snapshot-item"><span>Status</span><strong id="snapshotStatus">-</strong></div>
                                <div class="snapshot-item"><span>Budget</span><strong id="snapshotBudget">-</strong></div>
                                <div class="snapshot-item"><span>Planned duration</span><strong id="snapshotDuration">-</strong></div>
                                <div class="snapshot-item"><span>Workers</span><strong id="snapshotWorkers">-</strong></div>
                                <div class="snapshot-item"><span>Completion</span><strong id="snapshotCompletion">-</strong></div>
                                <div class="snapshot-item"><span>Recorded expenses</span><strong id="snapshotFinanceTotal">-</strong></div>
                                <div class="snapshot-item"><span>Material</span><strong id="snapshotMaterial">-</strong></div>
                                <div class="snapshot-item"><span>Labor</span><strong id="snapshotLabor">-</strong></div>
                                <div class="snapshot-item"><span>Equipment / Other</span><strong id="snapshotOther">-</strong></div>
                            </div>
                            <div class="form-actions">
                                <button id="predictCostButton" type="button" class="btn btn-primary analytics-button" onclick="predictCost()" disabled>
                                    Predict cost
                                </button>
                                <button type="button" class="btn btn-outline analytics-button" onclick="clearPredictionForm()">
                                    Clear selection
                                </button>
                            </div>
                        </div>
                    </form>

                    <div id="predictionResult" class="prediction-result">
                        <h4>Prediction Result</h4>
                        <div id="resultContent"></div>
                    </div>
                </section>

                <!-- Material Forecast -->
                <section class="card analytics-panel">
                    <div class="card-header">
                        <div>
                            <div class="card-title">30-Day Material Stock Projection</div>
                            <p class="analytics-panel-description">Expected demand based on recent dated inventory usage.</p>
                        </div>
                        <button class="btn btn-outline analytics-button analytics-button-small" type="button" onclick="loadMaterialForecast()">
                            Refresh
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="analytics-table">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Current Stock</th>
                                    <th>Avg Daily Usage</th>
                                    <th>30-Day Demand</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="materialForecastBody">
                                <tr>
                                    <td colspan="5" class="text-center">Loading forecast data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Right Column: Financial Analytics -->
            <div>
                <!-- Budget Variance -->
                <section class="card analytics-panel">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Budget Variance Analysis</div>
                            <p class="analytics-panel-description">Latest recorded spending compared with project budgets.</p>
                        </div>
                        <button class="btn btn-outline analytics-button analytics-button-small" type="button" onclick="loadBudgetVariance()">
                            Refresh
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="analytics-table">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Budget</th>
                                    <th>Actual</th>
                                    <th>Variance</th>
                                </tr>
                            </thead>
                            <tbody id="budgetVarianceBody">
                                <tr>
                                    <td colspan="4" class="text-center">Loading variance data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>

        <!-- Full-width model performance section -->
        <section class="card analytics-panel model-performance-card">
            <div class="card-header">
                <div>
                    <div class="card-title">Model Performance</div>
                    <p class="analytics-panel-description">Evaluation, selection, and governance details for the active prediction model.</p>
                </div>
                <span class="badge badge-info" id="samplesCount">0 samples</span>
            </div>
            <div id="modelMetrics">
                <section class="metric-section" aria-labelledby="modelQualityTitle">
                    <h3 class="metric-section-title" id="modelQualityTitle">Prediction quality</h3>
                    <div class="model-status-grid">
                        <div class="metric-item"><span class="metric-label">Avg. Closeness</span><span class="metric-value" id="metricAccuracy">-</span></div>
                        <div class="metric-item"><span class="metric-label">MAE</span><span class="metric-value" id="metricMAE">-</span></div>
                        <div class="metric-item"><span class="metric-label">R-Squared</span><span class="metric-value" id="metricRSquared">-</span></div>
                    </div>
                </section>
                <section class="metric-section" aria-labelledby="overrunDetectionTitle">
                    <h3 class="metric-section-title" id="overrunDetectionTitle">Cost-overrun detection at 5%</h3>
                    <div class="model-status-grid">
                        <div class="metric-item"><span class="metric-label">Precision</span><span class="metric-value" id="metricPrecision">-</span></div>
                        <div class="metric-item"><span class="metric-label">Recall</span><span class="metric-value" id="metricRecall">-</span></div>
                        <div class="metric-item"><span class="metric-label">F1 Score</span><span class="metric-value" id="metricF1">-</span></div>
                    </div>
                </section>
                <section class="metric-section" aria-labelledby="modelSelectionTitle">
                    <h3 class="metric-section-title" id="modelSelectionTitle">Model selection</h3>
                    <div class="model-status-grid">
                        <div class="metric-item"><span class="metric-label">Selected Split</span><span class="metric-value" id="metricSplit">-</span></div>
                        <div class="metric-item"><span class="metric-label">Feature Decision</span><span class="metric-value" id="metricFeatureDecision">-</span></div>
                        <div class="metric-item"><span class="metric-label">Model Comparison</span><span class="metric-value" id="metricModelComparison">-</span></div>
                    </div>
                </section>
                <section class="metric-section" aria-labelledby="modelGovernanceTitle">
                    <h3 class="metric-section-title" id="modelGovernanceTitle">Validation and governance</h3>
                    <div class="model-detail-grid">
                        <div class="metric-item metric-detail"><span class="metric-label">Validation</span><span class="metric-value" id="metricValidation">-</span></div>
                        <div class="metric-item metric-detail"><span class="metric-label">Finance Feature Gate</span><span class="metric-value" id="metricFinancePolicy">-</span></div>
                        <div class="metric-item metric-detail"><span class="metric-label">Holdout Monitoring</span><span class="metric-value" id="metricMonitoring">-</span></div>
                        <div class="metric-item metric-detail metric-interpretation"><span class="metric-label">Interpretation</span><span class="metric-value" id="metricInterpretation">-</span></div>
                    </div>
                </section>
            </div>
        </section>

        <!-- Footer -->
        <footer class="analytics-footer">Last updated <span id="lastUpdated">-</span></footer>
    </div>

    @if(auth()->check() && strtolower((string) auth()->user()->role) === 'admin')
        <div class="ml-confirm-overlay" id="retrainConfirmModal" hidden>
            <section class="ml-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="retrainConfirmTitle" aria-describedby="retrainConfirmDescription">
                <h2 id="retrainConfirmTitle">Retrain prediction model?</h2>
                <p id="retrainConfirmDescription">PFIMS will rebuild and evaluate the model using the latest eligible completed-project records. This can take a few moments.</p>
                <div class="ml-confirm-actions">
                    <button class="btn btn-outline" id="cancelRetrainButton" type="button" onclick="closeRetrainConfirmation()">Cancel</button>
                    <button class="btn btn-primary" id="confirmRetrainButton" type="button" onclick="confirmRetrainModel()">Retrain model</button>
                </div>
            </section>
        </div>
    @endif
    </div>

    <script>
    // ─── CONFIGURATION ─────────────────────────────────────────────
    const API_BASE = document.getElementById('predictiveAnalyticsRoot').dataset.apiBase;
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
    let predictionProjects = [];
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    })[character]);

    // ─── NOTIFICATION SYSTEM ──────────────────────────────────────
    function showNotification(message, type = 'info', duration = 5000) {
        const colors = {
            success: '#43a047',
            error: '#ef5350',
            info: '#1e88e5'
        };

        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.style.background = colors[type] || colors.info;
        notification.innerHTML = `
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.remove()">×</button>
        `;
        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, duration);
    }

    // ─── LOAD DASHBOARD DATA ──────────────────────────────────────
    async function loadDashboard() {
        try {
            const response = await fetch(`${API_BASE}/analytics/dashboard`, {
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN
                }
            });
            const data = await response.json();

            if (data.success) {
                updateModelMetrics(data.model_metrics);
                updateMaterialForecast(data.predictive?.material_forecast || {});
                updateBudgetVariance(data.diagnostic?.budget_variance || []);
                document.getElementById('lastUpdated').textContent = new Date().toLocaleString();
            } else {
                showNotification('Failed to load dashboard: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error('Dashboard error:', error);
            showNotification('Error loading dashboard: ' + error.message, 'error');
        }
    }

    // ─── UPDATE MODEL METRICS ─────────────────────────────────────
    function updateModelMetrics(metrics) {
        if (!metrics) return;

        const displayPercent = value => value === null || value === undefined || Number.isNaN(Number(value))
            ? '-' : Number(value).toFixed(2) + '%';
        const displayNumber = (value, decimals = 4) => value === null || value === undefined || Number.isNaN(Number(value))
            ? '-' : Number(value).toFixed(decimals);
        const accuracy = metrics.accuracy;
        const rSquared = metrics.r_squared;
        const samplesTrained = parseInt(metrics.samples_trained) || 0;
        const realSamples = parseInt(metrics.real_samples_available) || 0;

        document.getElementById('metricAccuracy').textContent = displayPercent(accuracy);
        document.getElementById('metricMAE').textContent = metrics.mae_formatted || 'Unavailable';
        document.getElementById('metricRSquared').textContent = displayNumber(rSquared);
        document.getElementById('metricPrecision').textContent = displayPercent(metrics.precision);
        document.getElementById('metricRecall').textContent = displayPercent(metrics.recall);
        document.getElementById('metricF1').textContent = displayPercent(metrics.f1_score);
        document.getElementById('metricSplit').textContent = (metrics.split_selection?.selected_method || metrics.evaluation_method || '-').replaceAll('_', ' ');
        document.getElementById('metricFeatureDecision').textContent = (metrics.feature_set?.decision || '-').replaceAll('_', ' ');
        const comparison = metrics.model_comparison;
        document.getElementById('metricModelComparison').textContent = comparison
            ? `${comparison.production_model.replaceAll('_', ' ')} retained; ${comparison.comparison_result.replaceAll('_', ' ')}`
            : '-';
        const cv = metrics.cross_validation;
        document.getElementById('metricValidation').textContent = cv
            ? `${cv.method.replaceAll('_', ' ')}: mean MAE ${metricsCurrency(cv.average_mean_absolute_error)}, mean MAPE ${displayPercent(cv.average_mean_absolute_percentage_error)}. ${metrics.split_selection?.scoring_rule || ''}`
            : 'Cross-validation is unavailable while the synthetic fallback is active.';
        const featureSet = metrics.feature_set;
        document.getElementById('metricFinancePolicy').textContent = featureSet
            ? `${(featureSet.decision || 'not evaluated').replaceAll('_', ' ')}. ${featureSet.finance_feature_leakage_note || 'Finance features require an as-of date and must pass the documented validation gate.'}`
            : 'Finance features are not evaluated while the synthetic fallback is active.';
        const monitoring = metrics.monitoring_segments;
        document.getElementById('metricMonitoring').textContent = monitoring
            ? `MAE, MAPE, precision, recall and F1 are reported by ${Object.keys(monitoring.by_project_size || {}).length} budget-size and ${Object.keys(monitoring.by_project_type || {}).length} project-type holdout segment(s).`
            : 'Segment monitoring is unavailable while the synthetic fallback is active.';
        document.getElementById('metricInterpretation').textContent = metrics.interpretation || 'No data available';
        document.getElementById('samplesCount').textContent = metrics.uses_synthetic_data
            ? `${samplesTrained} synthetic / ${realSamples} real`
            : `${samplesTrained} real samples`;
    }

    function metricsCurrency(value) {
        return value === null || value === undefined || Number.isNaN(Number(value))
            ? '-' : `₱${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    async function loadPredictionProjects(preserveSelection = true) {
        const select = document.getElementById('predictionProject');
        const previous = preserveSelection ? select.value : '';
        select.disabled = true;
        select.innerHTML = '<option value="">Loading eligible projects…</option>';

        try {
            const response = await fetch(`${API_BASE}/prediction-projects`, {
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN }
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load projects');

            predictionProjects = Array.isArray(data.projects) ? data.projects : [];
            select.innerHTML = '<option value="">Select a project…</option>';
            predictionProjects.forEach(project => {
                const option = document.createElement('option');
                option.value = String(project.project_id);
                option.textContent = `${project.project_name} — ${project.status}`;
                select.appendChild(option);
            });
            select.disabled = predictionProjects.length === 0;
            if (previous && predictionProjects.some(project => String(project.project_id) === previous)) {
                select.value = previous;
            }
            document.getElementById('predictionProjectNote').textContent = predictionProjects.length
                ? 'Inputs are loaded from the selected project, its latest budget, and finance expenses recorded through today.'
                : 'No eligible projects were found. Add an incomplete project with a budget, valid schedule, and workforce assignment.';
            updatePredictionProjectSnapshot();
        } catch (error) {
            predictionProjects = [];
            select.innerHTML = '<option value="">Unable to load projects</option>';
            document.getElementById('predictionProjectNote').textContent = error.message;
            updatePredictionProjectSnapshot();
        }
    }

    function updatePredictionProjectSnapshot() {
        const select = document.getElementById('predictionProject');
        const project = predictionProjects.find(item => String(item.project_id) === select.value);
        const snapshot = document.getElementById('predictionProjectSnapshot');
        const button = document.getElementById('predictCostButton');
        snapshot.hidden = !project;
        button.disabled = !project;
        if (!project) return;

        const currency = value => `₱${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        document.getElementById('snapshotStatus').textContent = project.status || 'Unspecified';
        document.getElementById('snapshotBudget').textContent = currency(project.budget);
        document.getElementById('snapshotDuration').textContent = `${project.duration} month${Number(project.duration) === 1 ? '' : 's'}`;
        document.getElementById('snapshotWorkers').textContent = Number(project.workers || 0).toLocaleString();
        document.getElementById('snapshotCompletion').textContent = `${Number(project.completion || 0).toFixed(1)}%`;
        document.getElementById('snapshotFinanceTotal').textContent = `${currency(project.fin_total_expense)}${project.finance_as_of_date ? ` through ${project.finance_as_of_date}` : ''}`;
        document.getElementById('snapshotMaterial').textContent = currency(project.fin_material_expense);
        document.getElementById('snapshotLabor').textContent = currency(project.fin_labor_expense);
        document.getElementById('snapshotOther').textContent = `${currency(project.fin_equipment_expense)} / ${currency(project.fin_other_expense)}`;
    }

    // ─── PREDICT COST ─────────────────────────────────────────────
    async function predictCost() {
        const form = document.getElementById('predictionForm');
        if (!form.reportValidity()) return;

        const projectId = document.getElementById('predictionProject').value;

        const resultDiv = document.getElementById('predictionResult');
        const contentDiv = document.getElementById('resultContent');

        resultDiv.className = 'prediction-result';
        resultDiv.style.display = 'block';
        contentDiv.innerHTML = '<div class="text-center"><span class="spinner"></span> Calculating prediction...</div>';

        try {
            const response = await fetch(`${API_BASE}/predict/cost`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify({ project_id: Number(projectId) })
            });

            const result = await response.json();

            if (result.success) {
                const variance = parseFloat(result.variance) || 0;
                const variancePercentage = parseFloat(result.variance_percentage) || 0;
                const isOverBudget = variance > 0;
                
                resultDiv.className = `prediction-result show ${isOverBudget ? 'warning' : 'success'}`;
                
                contentDiv.innerHTML = `
                    <div class="result-row">
                        <span class="result-label">Project</span>
                        <span class="result-value">${escapeHtml(result.input_features?.project_name || 'Selected project')}</span>
                    </div>
                    <div class="result-row">
                        <span class="result-label">Predicted Cost</span>
                        <span class="result-value">${result.formatted || '₱0'}</span>
                    </div>
                    <div class="result-row">
                        <span class="result-label">Budget Variance</span>
                        <span class="result-value ${isOverBudget ? 'negative' : 'positive'}">
                            ${isOverBudget ? '+ ' : '- '}₱${Math.abs(variance).toLocaleString()}
                            (${variancePercentage.toFixed(1)}%)
                        </span>
                    </div>
                    <div class="result-row">
                        <span class="result-label">Status</span>
                        <span class="result-value">${result.risk_level || result.status || 'Unknown'}</span>
                    </div>
                    <div class="result-row">
                        <span class="result-label">Business Action</span>
                        <span class="result-value" style="font-size:13px;">${result.business_action || 'Review forecast inputs and continue monitoring.'}</span>
                    </div>
                    <div class="result-row">
                        <span class="result-label">Prediction Source</span>
                        <span class="result-value">${String(result.prediction_source || 'unknown').replaceAll('_', ' ')}</span>
                    </div>
                    <div class="result-row">
                        <span class="result-label">Model Accuracy (holdout)</span>
                        <span class="result-value">${result.model_accuracy === null || result.model_accuracy === undefined ? 'Unavailable' : `${Number(result.model_accuracy).toFixed(2)}%`}</span>
                    </div>
                    <div class="result-row" style="border-bottom: none; margin-top: 8px; font-size: 13px; color: #666;">
                        <span class="result-label">Inputs</span>
                        <span>Budget: ₱${parseFloat(result.input_features?.budget || 0).toLocaleString()} | 
                              Duration: ${parseFloat(result.input_features?.duration_months || 0)}mo | 
                              Workers: ${parseFloat(result.input_features?.worker_count || 0)}</span>
                    </div>
                    ${(result.warnings || []).length ? `
                        <div style="margin-top:12px; padding:10px; background:#fff3e0; color:#8a4b08; border-radius:6px; font-size:12px;">
                            <strong>Reliability notes:</strong><br>${result.warnings.map(warning => `• ${warning}`).join('<br>')}
                        </div>` : ''}
                `;
                showNotification('Prediction completed successfully!', 'success');
            } else {
                resultDiv.className = 'prediction-result show danger';
                const errors = result.errors ? Object.values(result.errors).flat().join('<br>') : '';
                contentDiv.innerHTML = `<p style="color:#c62828;">❌ ${errors || result.message || 'Prediction failed'}</p>`;
                showNotification('Prediction failed: ' + (result.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            resultDiv.className = 'prediction-result show danger';
            contentDiv.innerHTML = `<p style="color:#c62828;">❌ Error: ${error.message}</p>`;
            showNotification('Error: ' + error.message, 'error');
        }
    }

    // ─── CLEAR PREDICTION FORM ────────────────────────────────────
    function clearPredictionForm() {
        document.getElementById('predictionProject').value = '';
        updatePredictionProjectSnapshot();
        document.getElementById('predictionResult').style.display = 'none';
        document.getElementById('predictionResult').className = 'prediction-result';
    }

    // ─── LOAD MATERIAL FORECAST ──────────────────────────────────
    async function loadMaterialForecast() {
        try {
            const response = await fetch(`${API_BASE}/predict/material-demand`, {
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN
                }
            });
            const data = await response.json();

            if (data.success) {
                updateMaterialForecast(data.predictions || {});
            } else {
                showNotification('Failed to load material forecast: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error('Material forecast error:', error);
            showNotification('Error loading material forecast: ' + error.message, 'error');
        }
    }

    // ─── UPDATE MATERIAL FORECAST ─────────────────────────────────
    function updateMaterialForecast(predictions) {
        const tbody = document.getElementById('materialForecastBody');
        
        if (!predictions || Object.keys(predictions).length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center">No material forecast data available</td></tr>`;
            return;
        }

        let html = '';
        const items = Object.values(predictions).slice(0, 10);
        
        items.forEach(item => {
            const statusClass = item.status === 'Reorder Needed' ? 'badge-danger' :
                               item.status === 'Low Stock' ? 'badge-warning' : 'badge-success';
            
            const currentStock = parseFloat(item.current_stock) || 0;
            const avgUsage = parseFloat(item.avg_usage) || 0;
            const projectedDemand = parseFloat(item.projected_demand) || 0;
            
            html += `
                <tr>
                    <td><strong>${item.item_name || 'Unknown'}</strong></td>
                    <td>${currentStock.toFixed(2)}</td>
                    <td>${avgUsage.toFixed(2)}</td>
                    <td>${projectedDemand.toFixed(2)}</td>
                    <td><span class="badge ${statusClass}">${item.status || 'Unknown'}</span></td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    // ─── LOAD BUDGET VARIANCE ────────────────────────────────────
    async function loadBudgetVariance() {
        try {
            const response = await fetch(`${API_BASE}/analytics/budget-variance`, {
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN
                }
            });
            const data = await response.json();

            if (data.success) {
                const varianceData = data.data || [];
                updateBudgetVariance(varianceData);
            } else {
                showNotification('Failed to load budget variance: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error('Budget variance error:', error);
            showNotification('Error loading budget variance: ' + error.message, 'error');
            const tbody = document.getElementById('budgetVarianceBody');
            tbody.innerHTML = `<tr><td colspan="4" class="text-center">Error loading data</td></tr>`;
        }
    }

    // ─── UPDATE BUDGET VARIANCE ────────────────────────────────────
    function updateBudgetVariance(varianceData) {
        const tbody = document.getElementById('budgetVarianceBody');
        
        if (!varianceData || varianceData.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center">No budget variance data available</td></tr>`;
            return;
        }

        let html = '';
        varianceData.slice(0, 5).forEach(item => {
            const budget = parseFloat(item.budget) || 0;
            const actualCost = parseFloat(item.actual_cost) || 0;
            const variance = parseFloat(item.variance) || 0;
            const variancePercentage = parseFloat(item.variance_percentage) || 0;
            
            const isOverBudget = variance < 0;
            const statusColor = isOverBudget ? '#c62828' : '#2e7d32';
            
            html += `
                <tr>
                    <td><strong>${item.project_name || 'Unnamed'}</strong></td>
                    <td>₱${budget.toLocaleString()}</td>
                    <td>₱${actualCost.toLocaleString()}</td>
                    <td style="color:${statusColor}; font-weight:600;">
                        ${isOverBudget ? '-' : '+'}₱${Math.abs(variance).toLocaleString()}
                        <br><small>(${variancePercentage.toFixed(1)}%)</small>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    // ─── RETRAIN MODEL ────────────────────────────────────────────
    function openRetrainConfirmation() {
        const modal = document.getElementById('retrainConfirmModal');
        if (!modal) return;
        modal.hidden = false;
        document.getElementById('confirmRetrainButton').focus();
    }

    function closeRetrainConfirmation() {
        const modal = document.getElementById('retrainConfirmModal');
        if (modal) modal.hidden = true;
    }

    async function confirmRetrainModel() {
        closeRetrainConfirmation();

        showNotification('🔄 Retraining model... Please wait.', 'info', 10000);

        try {
            const response = await fetch(`${API_BASE}/retrain`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN
                }
            });
            const data = await response.json();

            if (data.success) {
                showNotification('✅ Model retrained successfully!', 'success');
                loadDashboard();
            } else {
                showNotification('❌ Retraining failed: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            showNotification('❌ Error retraining model: ' + error.message, 'error');
        }
    }

    // ─── REFRESH DATA ─────────────────────────────────────────────
    function refreshData() {
        showNotification('🔄 Refreshing dashboard data...', 'info', 2000);
        loadDashboard();
        loadPredictionProjects();
        loadMaterialForecast();
        loadBudgetVariance();
    }

    // ─── KEYBOARD SHORTCUTS ──────────────────────────────────────
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeRetrainConfirmation();
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            predictCost();
        }
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            refreshData();
        }
    });

    // ─── INIT ─────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        loadDashboard();
        loadPredictionProjects(false);
        loadMaterialForecast();
        loadBudgetVariance();
        document.getElementById('lastUpdated').textContent = new Date().toLocaleString();
        document.getElementById('predictionProject').addEventListener('change', updatePredictionProjectSnapshot);
        document.getElementById('retrainConfirmModal')?.addEventListener('click', event => {
            if (event.target.id === 'retrainConfirmModal') closeRetrainConfirmation();
        });

        setInterval(() => {
            loadDashboard();
        }, 60000);

        if (document.body.classList.contains('embedded-ml-dashboard')) {
            const reportHeight = () => window.parent.postMessage({
                type: 'pfims-ml-height',
                height: document.documentElement.scrollHeight
            }, window.location.origin);
            reportHeight();
            window.addEventListener('load', reportHeight);
            new ResizeObserver(reportHeight).observe(document.body);
        }
    });
</script>

@unless($fragment)
</body>
</html>
@endunless
