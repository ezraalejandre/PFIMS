<!DOCTYPE html>
<html lang="en">
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
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
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
            grid-template-columns: 1fr 1fr;
            gap: 12px;
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

            .model-status-grid {
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
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}">
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body>

    <div class="dashboard-container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>🤖 AI/ML Analytics Dashboard</h1>
                <small>Centralized Material Logistic & Project Financial Tracking System</small>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline" onclick="refreshData()">
                    🔄 Refresh
                </button>
                <button class="btn btn-primary" onclick="retrainModel()">
                    🧠 Retrain Model
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-label">Total Projects</div>
                <div class="stat-value" id="totalProjects">-</div>
                <div class="stat-sub" id="activeProjects">Active: -</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Budget</div>
                <div class="stat-value" id="totalBudget">-</div>
                <div class="stat-sub" id="totalExpenses">Expenses: -</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Inventory Items</div>
                <div class="stat-value" id="totalItems">-</div>
                <div class="stat-sub" id="lowStockItems">Low Stock: -</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Model Accuracy</div>
                <div class="stat-value" id="modelAccuracy">-</div>
                <div class="stat-sub" id="modelStatus">Status: -</div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="main-grid">
            <!-- Left Column: Prediction -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">🎯 Project Cost Prediction</div>
                        <span class="badge badge-info">Powered by Linear Regression</span>
                    </div>

                    <form id="predictionForm" onsubmit="return false;">
                        <div class="prediction-form">
                            <div class="form-group">
                                <label for="budget">📊 Project Budget (₱)</label>
                                <input type="number" id="budget" placeholder="e.g., 1000000" required>
                            </div>
                            <div class="form-group">
                                <label for="duration">📅 Duration (months)</label>
                                <input type="number" id="duration" placeholder="e.g., 6" required>
                            </div>
                            <div class="form-group">
                                <label for="workers">👷 Worker Count</label>
                                <input type="number" id="workers" placeholder="e.g., 10" value="5">
                            </div>
                            <div class="form-group">
                                <label for="completion">📈 Completion (%)</label>
                                <input type="number" id="completion" placeholder="e.g., 50" value="0" min="0" max="100">
                            </div>
                            <div class="form-group">
                                <label for="materialCost">🧱 Material Cost (₱)</label>
                                <input type="number" id="materialCost" placeholder="e.g., 500000" value="0">
                            </div>
                            <div class="form-group">
                                <label for="laborCost">👨‍🔧 Labor Cost (₱)</label>
                                <input type="number" id="laborCost" placeholder="e.g., 300000" value="0">
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-primary" onclick="predictCost()" style="flex:1;">
                                    🔮 Predict Cost
                                </button>
                                <button type="button" class="btn btn-outline" onclick="clearPredictionForm()" style="flex:0.5; border:2px solid #ddd; color:#666; background:white;">
                                    Clear
                                </button>
                            </div>
                        </div>
                    </form>

                    <div id="predictionResult" class="prediction-result">
                        <h4>📋 Prediction Result</h4>
                        <div id="resultContent"></div>
                    </div>
                </div>

                <!-- Material Forecast -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">📦 Material Demand Forecast</div>
                        <button class="btn btn-success" onclick="loadMaterialForecast()" style="padding:6px 14px; font-size:12px;">
                            🔄 Refresh
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Current Stock</th>
                                    <th>Avg Usage</th>
                                    <th>Projected Demand</th>
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
                </div>
            </div>

            <!-- Right Column: Model Status & Analytics -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">📊 Model Performance</div>
                        <span class="badge badge-info" id="samplesCount">0 samples</span>
                    </div>
                    <div class="model-status-grid" id="modelMetrics">
                        <div class="metric-item">
                            <span class="metric-label">Accuracy</span>
                            <span class="metric-value" id="metricAccuracy">-</span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">MAE</span>
                            <span class="metric-value" id="metricMAE">-</span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">R-Squared</span>
                            <span class="metric-value" id="metricRSquared">-</span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">F1 Score</span>
                            <span class="metric-value" id="metricF1">-</span>
                        </div>
                        <div class="metric-item" style="grid-column: 1 / -1; background: #f0f4ff; border-left: 4px solid #1a237e;">
                            <span class="metric-label">📝 Interpretation</span>
                            <span class="metric-value" id="metricInterpretation" style="font-size:13px; font-weight:400; color:#555;">-</span>
                        </div>
                    </div>
                </div>

                <!-- Budget Variance -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">💰 Budget Variance Analysis</div>
                        <button class="btn btn-success" onclick="loadBudgetVariance()" style="padding:6px 14px; font-size:12px;">
                            🔄 Refresh
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table>
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
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align:center; padding:20px; color:#888; font-size:13px; border-top:1px solid #e0e0e0; margin-top:10px;">
            Centralized Material Logistic & Project Financial Tracking System | ML Dashboard v1.0
            <br>
            <span style="font-size:11px;">Last updated: <span id="lastUpdated">-</span></span>
        </div>
    </div>

    <script>
    // ─── CONFIGURATION ─────────────────────────────────────────────
    const API_BASE = '/api/ml';
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

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
                updateStats(data.descriptive);
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

    // ─── UPDATE STATS ─────────────────────────────────────────────
    function updateStats(descriptive) {
        if (!descriptive) return;

        const totalProjects = parseInt(descriptive.total_projects) || 0;
        const activeProjects = parseInt(descriptive.active_projects) || 0;
        const totalBudget = parseFloat(descriptive.total_budget) || 0;
        const totalExpenses = parseFloat(descriptive.total_expenses) || 0;
        const totalItems = parseInt(descriptive.total_inventory_items) || 0;

        document.getElementById('totalProjects').textContent = totalProjects;
        document.getElementById('activeProjects').textContent = `Active: ${activeProjects}`;

        document.getElementById('totalBudget').textContent = '₱' + totalBudget.toLocaleString();
        document.getElementById('totalExpenses').textContent = 'Expenses: ₱' + totalExpenses.toLocaleString();

        document.getElementById('totalItems').textContent = totalItems;
        
        if (descriptive.material_status && Array.isArray(descriptive.material_status)) {
            const lowStock = descriptive.material_status.filter(m => {
                const status = m.status || '';
                return status === 'Low Stock' || status === 'Reorder Needed';
            }).length;
            document.getElementById('lowStockItems').textContent = `Low Stock: ${lowStock}`;
        }
    }

    // ─── UPDATE MODEL METRICS ─────────────────────────────────────
    function updateModelMetrics(metrics) {
        if (!metrics) return;

        const accuracy = parseFloat(metrics.accuracy) || 0;
        const mae = parseFloat(metrics.mean_absolute_error) || 0;
        const rSquared = parseFloat(metrics.r_squared) || 0;
        const f1Score = parseFloat(metrics.f1_score) || 0;
        const samplesTrained = parseInt(metrics.samples_trained) || 0;

        document.getElementById('metricAccuracy').textContent = accuracy ? accuracy + '%' : '-';
        document.getElementById('metricMAE').textContent = metrics.mae_formatted || '₱' + mae.toLocaleString();
        document.getElementById('metricRSquared').textContent = rSquared ? rSquared.toFixed(4) : '-';
        document.getElementById('metricF1').textContent = f1Score ? f1Score.toFixed(4) : '-';
        document.getElementById('metricInterpretation').textContent = metrics.interpretation || 'No data available';
        document.getElementById('samplesCount').textContent = samplesTrained + ' samples';
        document.getElementById('modelAccuracy').textContent = accuracy ? accuracy + '%' : '-';
        document.getElementById('modelStatus').textContent = metrics.status || 'Unknown';
    }

    // ─── PREDICT COST ─────────────────────────────────────────────
    async function predictCost() {
        const budget = document.getElementById('budget').value;
        const duration = document.getElementById('duration').value;
        const workers = document.getElementById('workers').value || 5;
        const completion = document.getElementById('completion').value || 0;
        const materialCost = document.getElementById('materialCost').value || 0;
        const laborCost = document.getElementById('laborCost').value || 0;

        if (!budget || !duration) {
            showNotification('Please enter both budget and duration', 'error');
            return;
        }

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
                body: JSON.stringify({
                    budget: parseFloat(budget) || 0,
                    duration: parseFloat(duration) || 0,
                    workers: parseFloat(workers) || 0,
                    completion: parseFloat(completion) || 0,
                    material_cost: parseFloat(materialCost) || 0,
                    labor_cost: parseFloat(laborCost) || 0
                })
            });

            const result = await response.json();

            if (result.success) {
                const variance = parseFloat(result.variance) || 0;
                const variancePercentage = parseFloat(result.variance_percentage) || 0;
                const isOverBudget = variance > 0;
                
                resultDiv.className = `prediction-result show ${isOverBudget ? 'warning' : 'success'}`;
                
                contentDiv.innerHTML = `
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
                        <span class="result-value">${result.status || 'Unknown'}</span>
                    </div>
                    <div class="result-row" style="border-bottom: none; margin-top: 8px; font-size: 13px; color: #666;">
                        <span class="result-label">Inputs</span>
                        <span>Budget: ₱${parseFloat(result.input_features?.budget || 0).toLocaleString()} | 
                              Duration: ${parseFloat(result.input_features?.duration_months || 0)}mo | 
                              Workers: ${parseFloat(result.input_features?.worker_count || 0)}</span>
                    </div>
                `;
                showNotification('Prediction completed successfully!', 'success');
            } else {
                resultDiv.className = 'prediction-result show danger';
                contentDiv.innerHTML = `<p style="color:#c62828;">❌ ${result.message || 'Prediction failed'}</p>`;
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
        document.getElementById('budget').value = '';
        document.getElementById('duration').value = '';
        document.getElementById('workers').value = '5';
        document.getElementById('completion').value = '0';
        document.getElementById('materialCost').value = '0';
        document.getElementById('laborCost').value = '0';
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
    async function retrainModel() {
        if (!confirm('Retraining the model may take a few moments. Continue?')) return;

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
        loadMaterialForecast();
        loadBudgetVariance();
    }

    // ─── KEYBOARD SHORTCUTS ──────────────────────────────────────
    document.addEventListener('keydown', function(e) {
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
        loadMaterialForecast();
        loadBudgetVariance();
        document.getElementById('lastUpdated').textContent = new Date().toLocaleString();

        setInterval(() => {
            loadDashboard();
        }, 60000);
    });
</script>

</body>
</html>
