@php
    $fragment = $fragment ?? false;
    $portal = $portal ?? 'admin';
    $analyticsSection = request()->query('section', 'predictive');
    $parentModule = match ($analyticsSection) {
        'budget-comparison' => 'finance',
        'material-projection' => 'inventory',
        default => 'projects',
    };
    $moduleStylesheet = match ($parentModule) {
        'finance' => 'finance.css',
        'inventory' => 'inventory.css',
        default => 'projtracking.css',
    };
    $modulePageClass = match ($parentModule) {
        'finance' => 'finance-page',
        'inventory' => 'inventory-page',
        default => 'projects-page',
    };
@endphp
@unless($fragment)
<!DOCTYPE html>
<html lang="en" class="{{ request()->boolean('embedded') ? 'embedded-ml-document' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ML Dashboard - Test Preview</title>
    <link rel="stylesheet" href="{{ asset('css/'.$moduleStylesheet) }}">
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
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 18px;
        }

        .prediction-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            align-items: stretch;
            margin-bottom: 18px;
        }

        .prediction-row > div,
        .prediction-row .prediction-workspace {
            min-width: 0;
            height: 100%;
        }

        .prediction-row .model-performance-card {
            grid-column: 1 / -1;
            height: auto;
        }

        @media (max-width: 1024px) {
            .main-grid,
            .prediction-row {
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
            align-items: flex-start;
            gap: 18px;
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
            text-align: right;
            overflow-wrap: anywhere;
        }

        .prediction-result .result-value.narrative {
            max-width: 72%;
            font-size: 13px;
            line-height: 1.55;
            font-weight: 500;
        }

        .prediction-result .result-helper {
            margin: 12px 0 4px;
            color: #475569;
            font-size: 12px;
            line-height: 1.5;
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
    <link rel="stylesheet" href="{{ asset('css/'.$portal.'.css') }}">
    <link rel="stylesheet" href="{{ asset('css/centralized-predictive-analytics.css') }}?v={{ filemtime(public_path('css/centralized-predictive-analytics.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}?v={{ filemtime(public_path('css/ui-refresh.css')) }}">
    <script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
</head>
<body class="{{ $modulePageClass }} analytics-module-page" data-portal="{{ $portal }}" data-parent-module="{{ $parentModule }}">
@endunless

@unless($fragment)
    <header class="top-header">
        <div class="left">
            <img src="{{ asset('images/logo.jpg') }}" alt="Logo">
            <div class="brand-text">PFIMS<small>E.V. Catapang Design-Construction &amp; Supply</small></div>
        </div>
        <div class="right">
            <a href="{{ url('/notifications') }}" aria-label="Open alerts">
                <img src="{{ asset('images/notif.jpg') }}" alt="" style="height: 22px; width: auto; cursor: pointer;">
            </a>
            <a href="{{ url('/profile') }}" style="display: flex; align-items: center; gap: 5px; color: inherit; text-decoration: none;">
                <img src="{{ asset('images/user.jpg') }}" alt="" style="height: 30px; width: 30px; border-radius: 50%; object-fit: cover;">
                <span>{{ auth()->user()->name === 'Administrator' ? 'Admin' : auth()->user()->name }}</span>
            </a>
        </div>
    </header>

    <aside class="sidebar">
        <nav aria-label="Primary navigation">
            <ul>
                <li><a href="{{ url('/dashboard') }}"><img src="{{ asset('images/dashboard.png') }}" alt="" class="nav-link-icon">DASHBOARD</a></li>
                <li class="{{ $parentModule === 'projects' ? 'active' : '' }}"><a href="{{ url('/projects') }}"><img src="{{ asset('images/projects.png') }}" alt="" class="nav-link-icon">PROJECTS</a></li>
                <li class="{{ $parentModule === 'finance' ? 'active' : '' }}"><a href="{{ url('/finance') }}"><img src="{{ asset('images/finance.png') }}" alt="" class="nav-link-icon">FINANCE</a></li>
                <li class="{{ $parentModule === 'inventory' ? 'active' : '' }}"><a href="{{ url('/inventory') }}"><img src="{{ asset('images/inventory.png') }}" alt="" class="nav-link-icon">INVENTORY</a></li>
                <li><a href="{{ url('/reports') }}"><img src="{{ asset('images/reports.png') }}" alt="" class="nav-link-icon">REPORTS</a></li>
            </ul>
        </nav>
        <div class="bottom-nav">
            <ul>
                <li><a href="{{ url('/settings') }}"><img src="{{ asset('images/settings.jpg') }}" alt="" class="nav-icon">Settings</a></li>
                <li class="logout">
                    <form method="POST" action="{{ url('/logout') }}" style="width: 100%; margin: 0; padding: 0;">
                        @csrf
                        <button type="submit" style="display: flex; align-items: center; gap: 12px; width: 100%; background: none; border: none; cursor: pointer; padding: 0; font: inherit; color: inherit;">
                            <img src="{{ asset('images/logout.jpg') }}" alt="" class="nav-icon">Log out
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </aside>

    <main class="main-content">
@endunless

@unless($fragment)
    <div class="analytics-top-spacer" aria-hidden="true"></div>
@endunless

    <div id="predictiveAnalyticsRoot" class="predictive-analytics-root embedded-ml-dashboard" data-api-base="{{ url('/api/ml') }}">
    <div class="dashboard-container analytics-shell">
        <!-- Main Grid -->
        @if($portal === 'admin')
        <div class="prediction-row analytics-tab-content" id="costPredictionSection" data-analytics-content @if($analyticsSection !== 'predictive') hidden @endif>
            <!-- Left Column: Prediction -->
            <div>
                <section class="card analytics-panel prediction-workspace">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Project Cost Prediction</div>
                            <p class="analytics-panel-description">Select an eligible project to forecast its final cost from current system records.</p>
                        </div>
                    </div>
                    <form id="predictionForm" onsubmit="return false;">
                        <div class="project-selection">
                            <div class="form-group">
                                <label for="predictionProject">Project to predict</label>
                                <select id="predictionProject" required disabled>
                                    <option value="">Loading eligible projects…</option>
                                </select>
                            </div>
                            <p class="project-selection-note" id="predictionProjectNote">Choose an active project with complete budget and schedule details.</p>
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

                </section>
            </div>
            <div>
                <section class="card analytics-panel prediction-workspace">
                    <div class="card-header"><div class="card-title">Prediction Result</div></div>
                    <div id="predictionResult" class="prediction-result"><div id="resultContent"></div></div>
                </section>
            </div>
            <!-- Model quality is part of the project cost prediction workflow. -->
            <section class="card analytics-panel model-performance-card" aria-labelledby="modelPerformanceTitle">
                <div class="card-header">
                    <div>
                        <div class="card-title" id="modelPerformanceTitle">Model Performance</div>
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
        </div>
        @endif

            <div class="analytics-tab-content" id="materialProjectionSection" data-analytics-content @if($analyticsSection !== 'material-projection') hidden @endif>
                <!-- Material Forecast -->
                <section class="card analytics-panel">
                    <div class="card-header">
                        <div>
                            <div class="card-title">30-Day Material Stock Projection</div>
                            <p class="analytics-panel-description">Expected demand based on recent dated inventory usage.</p>
                        </div>
                    </div>
                    <div class="filters-grid" aria-label="Material Projection filters">
                        <label class="filter-control">Search<input id="materialForecastSearch" type="search" maxlength="100" placeholder="Material name"></label>
                        <label class="filter-control">Stock status<select id="materialForecastStatus"><option value="">All stock states</option><option>Healthy</option><option>Low Stock</option><option>Reorder Needed</option></select></label>
                        <button type="button" id="clearMaterialForecastFilters">Clear</button>
                    </div>
                    <div class="table-wrapper">
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
                    <div class="pagination-wrapper" id="materialForecastPagination">
                        <div class="rows-info">
                            <span>Rows per page</span>
                            <select id="materialForecastPageSize" aria-label="Material Projection rows per page">
                                <option value="5" selected>5</option>
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <span id="materialForecastRange" class="pagination-info">Showing 0–0 of 0</span>
                        </div>
                        <div class="pagination-links" id="materialForecastPaginationLinks" aria-label="Material Projection pages"></div>
                    </div>
                </section>
            </div>

            <!-- Right Column: Financial Analytics -->
            <div class="analytics-tab-content" id="budgetComparisonSection" data-analytics-content @if($analyticsSection !== 'budget-comparison') hidden @endif>
                <!-- Budget Variance -->
                <section class="card analytics-panel">
                    <div class="card-header">
                        <div>
                    <div class="card-title">Budget-Spending Comparison</div>
                            <p class="analytics-panel-description">Every recorded budget compared with project spending. Position is <strong>Within budget</strong> when Budget − Actual is zero or positive, and <strong>Over budget</strong> when it is negative.</p>
                        </div>
                    </div>
                    <div class="filters-grid" aria-label="Budget comparison filters">
                        <label class="filter-control">Search<input id="budgetVarianceSearch" type="search" maxlength="100" placeholder="Project name"></label>
                        <label class="filter-control">Project<select id="budgetVarianceProject"><option value="">All projects</option></select></label>
                        <label class="filter-control">Position<select id="budgetVarianceStatus"><option value="">All positions</option><option value="within">Within budget</option><option value="over">Over budget</option></select></label>
                        <button type="button" id="clearBudgetVarianceFilters">Clear</button>
                    </div>
                    <div class="table-wrapper">
                        <table class="analytics-table">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Budget</th>
                                    <th>Actual</th>
                                    <th>Budget Difference</th>
                                </tr>
                            </thead>
                            <tbody id="budgetVarianceBody">
                                <tr>
                                    <td colspan="4" class="text-center">Loading variance data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination-wrapper" id="budgetVariancePagination">
                        <div class="rows-info">
                            <span>Rows per page</span>
                            <select id="budgetVariancePageSize" aria-label="Budget-Spending Comparison rows per page">
                                <option value="5" selected>5</option>
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <span id="budgetVarianceRange" class="pagination-info">Showing 0–0 of 0</span>
                        </div>
                        <div class="pagination-links" id="budgetVariancePaginationLinks" aria-label="Budget-Spending Comparison pages"></div>
                    </div>
                </section>
            </div>

        <!-- Footer -->
        <footer class="analytics-footer">Last updated <span id="lastUpdated">-</span></footer>
    </div>

    </div>

@unless($fragment)
    </main>
@endunless

    <script>
    // ─── CONFIGURATION ─────────────────────────────────────────────
    const API_BASE = document.getElementById('predictiveAnalyticsRoot').dataset.apiBase;
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
    const requestedAnalyticsSection = new URLSearchParams(window.location.search).get('section');
    const currentPortal = document.body.dataset.portal || 'admin';
    let predictionProjects = [];
    let materialForecastRows = [];
    let materialForecastPage = 1;
    let budgetVarianceRows = [];
    let budgetVariancePage = 1;
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    })[character]);

    // ─── NOTIFICATION SYSTEM ──────────────────────────────────────
    function showNotification(message, type = 'info', duration = 5000) {
        if (typeof window.showPfimsAlert === 'function') {
            window.showPfimsAlert(message, type, duration);
            return;
        }
        const notification = document.createElement('div');
        notification.className = `pfims-prediction-notice ${type}`;
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
                const resultTone = isOverBudget ? 'warning' : 'success';
                const predictedCost = parseFloat(result.predicted_cost || 0);
                const recordedExpenses = parseFloat(result.input_features?.fin_total_expense || 0);
                const completion = parseFloat(result.input_features?.completion_percentage || 0);
                const remainingForecastCost = Math.max(predictedCost - recordedExpenses, 0);
                const varianceAmount = Math.abs(variance);
                const variancePercent = Math.abs(variancePercentage);
                const displayedStatus = isOverBudget
                    ? (variancePercentage > 10 ? 'Critical cost exposure' : (variancePercentage > 5 ? 'High cost exposure' : 'Manageable cost exposure'))
                    : 'Within approved budget';
                const businessDiagnostic = isOverBudget
                    ? `The project is forecast to exceed its approved budget by ${variancePercentage.toFixed(1)}%. Without corrective action, this reduces the expected project margin and may require additional funding approval.`
                    : `The project is forecast to finish ${variancePercent.toFixed(1)}% below its approved budget, leaving a projected savings margin that management should protect through the remaining work.`;
                const managementAction = isOverBudget
                    ? 'Review remaining commitments, validate high-cost work packages, and agree on a recovery plan before approving further discretionary spending.'
                    : 'Maintain current cost controls, confirm outstanding commitments, and monitor the remaining forecast against progress at the next management review.';
                const varianceLabel = isOverBudget ? 'Projected budget overrun' : 'Projected budget savings';
                const varianceDescription = isOverBudget
                    ? `₱${varianceAmount.toLocaleString()} (${variancePercent.toFixed(1)}% over budget)`
                    : `₱${varianceAmount.toLocaleString()} (${variancePercent.toFixed(1)}% under budget)`;
                resultDiv.className = `prediction-result show ${resultTone}`;
                
                contentDiv.innerHTML = `
                    <div class="result-row">
                        <span class="result-label">Project</span>
                        <span class="result-value">${escapeHtml(result.input_features?.project_name || 'Selected project')}</span>
                    </div>
                    <div class="result-row">
                        <span class="result-label">Forecast final cost</span>
                        <span class="result-value">${escapeHtml(result.formatted || '₱0')}</span>
                    </div>
                    <div class="result-row">
                        <span class="result-label">${varianceLabel}</span>
                        <span class="result-value ${isOverBudget ? 'negative' : 'positive'}">${varianceDescription}</span>
                    </div>
                    <div class="result-row">
                        <span class="result-label">Business position</span>
                        <span class="result-value">${escapeHtml(displayedStatus)}</span>
                    </div>
                    <div class="result-row">
                        <span class="result-label">Management diagnostic</span>
                        <span class="result-value narrative">${escapeHtml(businessDiagnostic)}</span>
                    </div>
                    <div class="result-row">
                        <span class="result-label">Remaining forecast cost</span>
                        <span class="result-value">₱${remainingForecastCost.toLocaleString()} after ₱${recordedExpenses.toLocaleString()} recorded expenses at ${completion.toFixed(1)}% completion</span>
                    </div>
                    <div class="result-row" style="border-bottom: none;">
                        <span class="result-label">Recommended action</span>
                        <span class="result-value narrative">${escapeHtml(managementAction)}</span>
                    </div>
                    <p class="result-helper"><strong>How to read this:</strong> A projected savings figure means the forecast is below the approved budget; an overrun means the forecast is above it. This view does not change the underlying prediction calculation.</p>
                `;
                showNotification('Prediction completed. The business diagnostic is ready.', 'success');
            } else {
                resultDiv.className = 'prediction-result show danger';
                const errors = result.errors ? Object.values(result.errors).flat().join('<br>') : '';
                contentDiv.innerHTML = `<p style="color:#c62828;">❌ ${escapeHtml(errors || result.message || 'Prediction failed')}</p>`;
                showNotification('Prediction failed: ' + (result.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            resultDiv.className = 'prediction-result show danger';
            contentDiv.innerHTML = `<p style="color:#c62828;">❌ Error: ${escapeHtml(error.message)}</p>`;
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
        if (predictions !== materialForecastRows) materialForecastRows = predictions ? Object.values(predictions) : [];
        const query = (document.getElementById('materialForecastSearch')?.value || '').trim().toLowerCase();
        const status = document.getElementById('materialForecastStatus')?.value || '';
        const filteredRows = materialForecastRows.filter(item => (!query || String(item.item_name || '').toLowerCase().includes(query)) && (!status || item.status === status));
        const pageSize = Number(document.getElementById('materialForecastPageSize')?.value || 5);
        const totalPages = Math.max(Math.ceil(filteredRows.length / pageSize), 1);
        materialForecastPage = Math.min(materialForecastPage, totalPages);
        const items = filteredRows.slice((materialForecastPage - 1) * pageSize, materialForecastPage * pageSize);

        if (items.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center">No material forecast data available</td></tr>`;
            document.getElementById('materialForecastRange').textContent = 'Showing 0–0 of 0';
            renderMaterialForecastPagination(totalPages);
            return;
        }

        let html = '';
        items.forEach(item => {
            const statusClass = item.status === 'Reorder Needed' ? 'badge-danger' :
                               item.status === 'Low Stock' ? 'badge-warning' : 'badge-success';
            
            const currentStock = parseFloat(item.current_stock) || 0;
            const avgUsage = parseFloat(item.avg_usage) || 0;
            const projectedDemand = parseFloat(item.projected_demand) || 0;
            
            html += `
                <tr>
                    <td><strong>${escapeHtml(item.item_name || 'Unknown')}</strong></td>
                    <td>${currentStock.toFixed(2)}</td>
                    <td>${avgUsage.toFixed(2)}</td>
                    <td>${projectedDemand.toFixed(2)}</td>
                    <td><span class="badge ${statusClass}">${item.status || 'Unknown'}</span></td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        const start = (materialForecastPage - 1) * pageSize + 1;
        const end = Math.min(materialForecastPage * pageSize, filteredRows.length);
        document.getElementById('materialForecastRange').textContent = `Total: ${filteredRows.length}`;
        renderMaterialForecastPagination(totalPages);
    }

    function compactPaginationItems(current, total) {
        if (total <= 7) return Array.from({ length: total }, (_, index) => index + 1);
        const items = [1];
        const from = Math.max(2, current - 1);
        const to = Math.min(total - 1, current + 1);
        if (from > 2) items.push('ellipsis-start');
        for (let page = from; page <= to; page += 1) items.push(page);
        if (to < total - 1) items.push('ellipsis-end');
        items.push(total);
        return items;
    }

    function renderMaterialForecastPagination(totalPages) {
        const links = document.getElementById('materialForecastPaginationLinks');
        if (!links) return;
        const button = (label, page, active = false, disabled = false) =>
            `<button type="button" data-material-page="${page}" class="${active ? 'active' : ''}" ${disabled ? 'disabled' : ''}>${label}</button>`;
        let html = button('Previous', materialForecastPage - 1, false, materialForecastPage <= 1);
        compactPaginationItems(materialForecastPage, totalPages).forEach(item => {
            html += typeof item === 'number'
                ? button(String(item), item, item === materialForecastPage)
                : '<span class="ellipsis" aria-hidden="true">…</span>';
        });
        html += button('Next', materialForecastPage + 1, false, materialForecastPage >= totalPages);
        links.innerHTML = html;
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
                showNotification('Failed to load the budget comparison: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error('Budget variance error:', error);
            showNotification('Error loading the budget comparison: ' + error.message, 'error');
            const tbody = document.getElementById('budgetVarianceBody');
            tbody.innerHTML = `<tr><td colspan="4" class="text-center">Error loading data</td></tr>`;
        }
    }

    // ─── UPDATE BUDGET VARIANCE ────────────────────────────────────
    function updateBudgetVariance(varianceData) {
        const tbody = document.getElementById('budgetVarianceBody');
        if (varianceData !== budgetVarianceRows) {
            budgetVarianceRows = Array.isArray(varianceData) ? varianceData : [];
            const projectFilter = document.getElementById('budgetVarianceProject');
            const selectedProject = projectFilter?.value || '';
            if (projectFilter) {
                const names = [...new Set(budgetVarianceRows.map(item => String(item.project_name || '').trim()).filter(Boolean))].sort();
                projectFilter.innerHTML = '<option value="">All projects</option>';
                names.forEach(name => {
                    const option = document.createElement('option');
                    option.value = name;
                    option.textContent = name;
                    projectFilter.appendChild(option);
                });
                if (names.includes(selectedProject)) projectFilter.value = selectedProject;
            }
        }
        const query = (document.getElementById('budgetVarianceSearch')?.value || '').trim().toLowerCase();
        const project = document.getElementById('budgetVarianceProject')?.value || '';
        const position = document.getElementById('budgetVarianceStatus')?.value || '';
        const filteredRows = budgetVarianceRows.filter(item => {
            const projectName = String(item.project_name || '');
            const matchesSearch = !query || projectName.toLowerCase().includes(query);
            const matchesProject = !project || projectName === project;
            const overBudget = Number(item.variance || 0) < 0;
            const rowPosition = String(item.position || (overBudget ? 'over' : 'within')).toLowerCase();
            return matchesSearch && matchesProject && (!position || rowPosition === position);
        });
        const pageSize = Number(document.getElementById('budgetVariancePageSize')?.value || 5);
        const totalPages = Math.max(Math.ceil(filteredRows.length / pageSize), 1);
        budgetVariancePage = Math.min(budgetVariancePage, totalPages);
        const visibleRows = filteredRows.slice((budgetVariancePage - 1) * pageSize, budgetVariancePage * pageSize);

        if (visibleRows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center">No budget comparison data available</td></tr>`;
            document.getElementById('budgetVarianceRange').textContent = 'Showing 0–0 of 0';
            renderBudgetVariancePagination(totalPages);
            return;
        }

        let html = '';
        visibleRows.forEach(item => {
            const budget = parseFloat(item.budget) || 0;
            const actualCost = parseFloat(item.actual_cost) || 0;
            const variance = parseFloat(item.variance) || 0;
            const variancePercentage = parseFloat(item.variance_percentage) || 0;
            
            const isOverBudget = variance < 0;
            const statusColor = isOverBudget ? '#c62828' : '#2e7d32';
            
            html += `
                <tr>
                    <td><strong>${escapeHtml(item.project_name || 'Unnamed')}</strong></td>
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
        const start = (budgetVariancePage - 1) * pageSize + 1;
        const end = Math.min(budgetVariancePage * pageSize, filteredRows.length);
        document.getElementById('budgetVarianceRange').textContent = `Total: ${filteredRows.length}`;
        renderBudgetVariancePagination(totalPages);
    }

    function renderBudgetVariancePagination(totalPages) {
        const links = document.getElementById('budgetVariancePaginationLinks');
        if (!links) return;
        const button = (label, page, active = false, disabled = false) =>
            `<button type="button" data-budget-page="${page}" class="${active ? 'active' : ''}" ${disabled ? 'disabled' : ''}>${label}</button>`;
        let html = button('Previous', budgetVariancePage - 1, false, budgetVariancePage <= 1);
        compactPaginationItems(budgetVariancePage, totalPages).forEach(item => {
            html += typeof item === 'number'
                ? button(String(item), item, item === budgetVariancePage)
                : '<span class="ellipsis" aria-hidden="true">…</span>';
        });
        html += button('Next', budgetVariancePage + 1, false, budgetVariancePage >= totalPages);
        links.innerHTML = html;
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

    // ─── KEYBOARD SHORTCUTS ──────────────────────────────────────
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeRetrainConfirmation();
        }
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            predictCost();
        }
    });

    // ─── INIT ─────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        const sectionMap = { predictive: 'costPredictionSection', 'material-projection': 'materialProjectionSection', 'budget-comparison': 'budgetComparisonSection' };
        const targetSection = sectionMap[requestedAnalyticsSection] || 'costPredictionSection';
        document.querySelectorAll('[data-analytics-content]').forEach(section => { section.hidden = section.id !== targetSection; });
        if (currentPortal === 'admin') loadDashboard();
        if (targetSection === 'costPredictionSection' && currentPortal === 'admin') {
            loadPredictionProjects(false);
        }
        if (targetSection === 'materialProjectionSection' && ['admin', 'operations'].includes(currentPortal)) {
            loadMaterialForecast();
        }
        if (targetSection === 'budgetComparisonSection' && ['admin', 'accounting'].includes(currentPortal)) {
            loadBudgetVariance();
        }
        document.getElementById('lastUpdated').textContent = new Date().toLocaleString();
        document.getElementById('predictionProject')?.addEventListener('change', updatePredictionProjectSnapshot);
        document.getElementById('materialForecastPageSize').addEventListener('change', () => {
            materialForecastPage = 1;
            updateMaterialForecast(materialForecastRows);
        });
        ['materialForecastSearch', 'materialForecastStatus'].forEach(id => document.getElementById(id).addEventListener('input', () => { materialForecastPage = 1; updateMaterialForecast(materialForecastRows); }));
        document.getElementById('clearMaterialForecastFilters').addEventListener('click', () => { document.getElementById('materialForecastSearch').value = ''; document.getElementById('materialForecastStatus').value = ''; materialForecastPage = 1; updateMaterialForecast(materialForecastRows); });
        document.getElementById('materialForecastPaginationLinks').addEventListener('click', event => {
            const button = event.target.closest('[data-material-page]');
            if (!button || button.disabled) return;
            materialForecastPage = Number(button.dataset.materialPage);
            updateMaterialForecast(materialForecastRows);
        });
        document.getElementById('budgetVariancePageSize').addEventListener('change', () => {
            budgetVariancePage = 1;
            updateBudgetVariance(budgetVarianceRows);
        });
        document.getElementById('budgetVariancePaginationLinks').addEventListener('click', event => {
            const button = event.target.closest('[data-budget-page]');
            if (!button || button.disabled) return;
            budgetVariancePage = Number(button.dataset.budgetPage);
            updateBudgetVariance(budgetVarianceRows);
        });
        ['budgetVarianceSearch', 'budgetVarianceProject', 'budgetVarianceStatus'].forEach(id => document.getElementById(id).addEventListener('input', () => { budgetVariancePage = 1; updateBudgetVariance(budgetVarianceRows); }));
        document.getElementById('clearBudgetVarianceFilters').addEventListener('click', () => {
            document.getElementById('budgetVarianceSearch').value = '';
            document.getElementById('budgetVarianceProject').value = '';
            document.getElementById('budgetVarianceStatus').value = '';
            budgetVariancePage = 1;
            updateBudgetVariance(budgetVarianceRows);
        });
        document.getElementById('retrainConfirmModal')?.addEventListener('click', event => {
            if (event.target.id === 'retrainConfirmModal') closeRetrainConfirmation();
        });
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
<script src="{{ asset('js/pfims-system-ui.js') }}?v={{ filemtime(public_path('js/pfims-system-ui.js')) }}"></script>
</body>
</html>
@endunless
