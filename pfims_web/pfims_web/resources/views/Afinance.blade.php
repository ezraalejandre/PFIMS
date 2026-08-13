<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Budget & Finance - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Afinance.css') }}">
    <style>
        /* ─── MODAL Z-INDEX ─── */
        #deleteConfirmModal { z-index: 9999 !important; }
        #deleteBudgetConfirmModal { z-index: 9999 !important; }
        #deleteBondConfirmModal { z-index: 9999 !important; }

        /* ─── REPORT TABS ─── */
        .report-tab {
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 8px;
            transition: 0.3s;
            border: 2px solid transparent;
        }
        .report-tab:hover { background: #f0f0f0; }
        .report-tab.active {
            background: #1a2b3c;
            color: #fff;
            border-color: #1a2b3c;
        }
        .report-section { display: none; }
        .report-section.active { display: block; }

        .report-table-wrapper {
            overflow-x: auto;
            margin-top: 15px;
            background: #fff;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .report-table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            min-width: 600px;
        }
        .report-table-wrapper table th {
            background: #f8f6f3;
            padding: 8px 10px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e9ecef;
            white-space: nowrap;
            font-size: 0.7rem;
        }
        .report-table-wrapper table td {
            padding: 6px 10px;
            border-bottom: 1px solid #f0f0f0;
            white-space: nowrap;
        }
        .report-table-wrapper table tr:hover { background: #faf8f5; }
        .report-table-wrapper table .total-row {
            font-weight: 700;
            background: #f8f6f3;
            border-top: 2px solid #ddd;
        }
        .report-table-wrapper table .sub-total-row {
            font-weight: 600;
            background: #faf8f5;
            border-top: 1px solid #e9ecef;
        }

        .amount-positive { color: #2e7d32; }
        .amount-negative { color: #d32f2f; font-weight: 600; }
        .amount-warning { color: #f57c00; }
        .budget-overrun { background: #ffebee !important; }
        .budget-warning { background: #fff3e0 !important; }
        .status-active { color: #2e7d32; }
        .status-released { color: #f57c00; }
        .status-forfeited { color: #d32f2f; }

        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-success { background: #e8f5e9; color: #2e7d32; }
        .badge-warning { background: #fff3e0; color: #f57c00; }
        .badge-danger { background: #ffebee; color: #d32f2f; }
        .badge-secondary { background: #f5f5f5; color: #888; }

        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 20px;
        }
        .filter-row select,
        .filter-row input {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 20px;
            background: #fff;
            font-size: 0.9rem;
            color: #333;
            outline: none;
            min-width: 180px;
            transition: 0.3s;
        }
        .filter-row select:focus,
        .filter-row input:focus {
            border-color: #c9a96e;
            box-shadow: 0 0 0 3px rgba(201,169,110,0.2);
        }
        .btn-clear-search {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 20px;
            background: #fff;
            cursor: pointer;
            color: #888;
            transition: 0.3s;
        }
        .btn-clear-search:hover {
            background: #f5f5f5;
            border-color: #c9a96e;
            color: #333;
        }

        .btn-add-data {
            background: #1a2b3c;
            color: #fff;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-add-data:hover {
            background: #2a3f54;
            transform: translateY(-1px);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
            max-width: 700px;
        }
        .stat-mini {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        .stat-mini .stat-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            margin-bottom: 4px;
        }
        .stat-mini .stat-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1a2b3c;
        }
        .stat-mini .stat-value.blue { color: #c9a96e; }
        .stat-mini .stat-value.red { color: #d32f2f; }
        .stat-mini .stat-value.green { color: #2e7d32; }

        .stats-row-budget {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-bottom: 25px;
            max-width: 200px;
        }
        .stats-row-budget .stat-mini {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        .stats-row-budget .stat-mini .stat-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            margin-bottom: 4px;
        }
        .stats-row-budget .stat-mini .stat-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1a2b3c;
        }
        .stats-row-budget .stat-mini .stat-value.blue { color: #c9a96e; }

        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0 0;
            flex-wrap: wrap;
            gap: 15px;
        }
        .pagination-wrapper .rows-info {
            font-size: 0.85rem;
            color: #888;
        }
        .pagination-wrapper .rows-info select {
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.85rem;
            background: #fafafa;
            cursor: pointer;
        }
        .pagination-links {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .pagination-links a,
        .pagination-links span {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #555;
            text-decoration: none;
            transition: 0.2s;
            cursor: pointer;
        }
        .pagination-links a:hover { background: #e9ecef; }
        .pagination-links .active {
            background: #1a2b3c;
            color: #fff;
        }
        .pagination-links .dots { color: #aaa; cursor: default; }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1500;
            display: none;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.active { display: flex; }
        .modal-container {
            background: #fff;
            width: 600px;
            max-width: 95%;
            max-height: 90vh;
            border-radius: 16px;
            padding: 30px 35px;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: fadeInScale 0.25s ease;
        }
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: #1a2b3c;
            margin: 0;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: #888;
            transition: 0.2s;
            padding: 0 8px;
            line-height: 1;
        }
        .modal-close:hover { color: #333; }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: #333;
            margin-bottom: 4px;
        }
        .form-group label .required { color: #d32f2f; }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: 0.3s;
            background: #fafafa;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #c9a96e;
            box-shadow: 0 0 0 3px rgba(201,169,110,0.2);
            background: #fff;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-row .form-group { margin-bottom: 0; }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 10px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        .modal-footer button {
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: 0.3s;
            border: none;
        }
        .btn-cancel {
            background: transparent;
            color: #888;
        }
        .btn-cancel:hover {
            color: #333;
            background: #f5f5f5;
        }
        .btn-save {
            background: #c9a96e;
            color: #fff;
        }
        .btn-save:hover {
            background: #b8975a;
            transform: translateY(-1px);
        }
        .btn-delete {
            background: #d32f2f;
            color: #fff;
        }
        .btn-delete:hover {
            background: #b71c1c;
            transform: translateY(-2px);
        }
        .btn-edit-project {
            background: #c9a96e;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-edit-project:hover {
            background: #b8975a;
            transform: translateY(-2px);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 30px;
            padding: 20px;
            background: #faf8f5;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        .detail-item label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            margin-bottom: 2px;
        }
        .detail-value {
            display: block;
            padding: 6px 0;
            color: #333;
            font-weight: 500;
        }
        .detail-edit {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            background: #fafafa;
        }
        .detail-edit:focus {
            outline: none;
            border-color: #c9a96e;
            box-shadow: 0 0 0 3px rgba(201,169,110,0.2);
            background: #fff;
        }

        .budget-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 30px;
            padding: 20px;
            background: #faf8f5;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .budget-detail-item {
            display: flex;
            flex-direction: column;
        }
        .budget-detail-item label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            margin-bottom: 2px;
        }
        .budget-detail-value {
            display: block;
            padding: 6px 0;
            color: #333;
            font-weight: 500;
        }
        .budget-detail-edit {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            background: #fafafa;
        }
        .budget-detail-edit:focus {
            outline: none;
            border-color: #c9a96e;
            box-shadow: 0 0 0 3px rgba(201,169,110,0.2);
            background: #fff;
        }

        .error-notification,
        .success-notification {
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999 !important;
            padding: 14px 30px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
            min-width: 350px;
            max-width: 90%;
            animation: slideDown 0.4s ease;
        }
        .error-notification { background: #d32f2f; color: #fff; }
        .success-notification { background: #2e7d32; color: #fff; }
        .error-notification .error-content,
        .success-notification .success-content {
            display: flex;
            align-items: center;
            gap: 12px;
            justify-content: space-between;
        }
        .error-notification .error-close,
        .success-notification .success-close {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0.8;
            padding: 0 8px;
        }
        .error-notification .error-close:hover,
        .success-notification .success-close:hover { opacity: 1; }
        @keyframes slideDown {
            from { transform: translateX(-50%) translateY(-30px); opacity: 0; }
            to { transform: translateX(-50%) translateY(0); opacity: 1; }
        }

        @media (max-width: 650px) {
            .stats-row { grid-template-columns: 1fr; max-width: 100%; }
            .stats-row-budget { grid-template-columns: 1fr; max-width: 100%; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-row select, .filter-row input { width: 100%; min-width: auto; }
            .modal-container { padding: 20px; width: 95%; }
            .detail-grid { grid-template-columns: 1fr; gap: 12px; padding: 15px; }
            .budget-detail-grid { grid-template-columns: 1fr; gap: 12px; padding: 15px; }
            .modal-footer { flex-direction: column; align-items: stretch; }
            .modal-footer button { width: 100%; text-align: center; }
            .form-row { grid-template-columns: 1fr; }
            .error-notification, .success-notification {
                min-width: auto;
                width: 90%;
                top: 75px;
                padding: 12px 18px;
            }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}">
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body class="finance-page">

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
            <span id="successMessage">Saved successfully!</span>
            <button class="success-close" onclick="closeSuccess()">×</button>
        </div>
    </div>

    <!-- ─── DELETE CONFIRMATION MODAL (Expense) ─── -->
    <div id="deleteConfirmModal" class="modal-overlay" style="display: none; z-index: 9999;">
        <div class="modal-container" style="width: 400px; max-width: 95%;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="modal-close" onclick="closeDeleteModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteConfirmMessage" style="font-size:1rem;color:#333;margin-bottom:10px;">Are you sure you want to permanently delete this item?</p>
                <p style="font-size:0.85rem;color:#888;margin-bottom:20px;">This action cannot be undone.</p>
            </div>
            <div class="modal-footer" style="display:flex;justify-content:center;gap:12px;margin-top:10px;padding-top:20px;border-top:1px solid #e9ecef;">
                <button class="btn-cancel" onclick="closeDeleteModal()" style="padding:10px 24px;border-radius:8px;font-weight:600;font-size:0.9rem;cursor:pointer;border:none;background:transparent;color:#888;transition:0.3s;">Cancel</button>
                <button class="btn-delete" id="confirmDeleteBtn" onclick="confirmDelete()" style="padding:10px 24px;border-radius:8px;font-weight:600;font-size:0.9rem;cursor:pointer;border:none;background:#d32f2f;color:#fff;transition:0.3s;">Delete</button>
            </div>
        </div>
    </div>

    <!-- ─── DELETE CONFIRMATION MODAL (Budget) ─── -->
    <div id="deleteBudgetConfirmModal" class="modal-overlay" style="display: none; z-index: 9999;">
        <div class="modal-container" style="width: 400px; max-width: 95%;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="modal-close" onclick="closeBudgetDeleteModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteBudgetConfirmMessage" style="font-size:1rem;color:#333;margin-bottom:10px;">Are you sure you want to permanently delete this budget?</p>
                <p style="font-size:0.85rem;color:#888;margin-bottom:20px;">This action cannot be undone.</p>
            </div>
            <div class="modal-footer" style="display:flex;justify-content:center;gap:12px;margin-top:10px;padding-top:20px;border-top:1px solid #e9ecef;">
                <button class="btn-cancel" onclick="closeBudgetDeleteModal()" style="padding:10px 24px;border-radius:8px;font-weight:600;font-size:0.9rem;cursor:pointer;border:none;background:transparent;color:#888;transition:0.3s;">Cancel</button>
                <button class="btn-delete" id="confirmBudgetDeleteBtn" onclick="confirmBudgetDelete()" style="padding:10px 24px;border-radius:8px;font-weight:600;font-size:0.9rem;cursor:pointer;border:none;background:#d32f2f;color:#fff;transition:0.3s;">Delete</button>
            </div>
        </div>
    </div>

    <!-- ─── DELETE CONFIRMATION MODAL (Bond) ─── -->
    <div id="deleteBondConfirmModal" class="modal-overlay" style="display: none; z-index: 9999;">
        <div class="modal-container" style="width: 400px; max-width: 95%;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="modal-close" onclick="closeBondDeleteModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteBondConfirmMessage" style="font-size:1rem;color:#333;margin-bottom:10px;">Are you sure you want to permanently delete this bond?</p>
                <p style="font-size:0.85rem;color:#888;margin-bottom:20px;">This action cannot be undone.</p>
            </div>
            <div class="modal-footer" style="display:flex;justify-content:center;gap:12px;margin-top:10px;padding-top:20px;border-top:1px solid #e9ecef;">
                <button class="btn-cancel" onclick="closeBondDeleteModal()" style="padding:10px 24px;border-radius:8px;font-weight:600;font-size:0.9rem;cursor:pointer;border:none;background:transparent;color:#888;transition:0.3s;">Cancel</button>
                <button class="btn-delete" id="confirmBondDeleteBtn" onclick="confirmBondDelete()" style="padding:10px 24px;border-radius:8px;font-weight:600;font-size:0.9rem;cursor:pointer;border:none;background:#d32f2f;color:#fff;transition:0.3s;">Delete</button>
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
                <li><a href="{{ url('/adashboard') }}">DASHBOARD</a></li>
                <li class="active"><a href="{{ url('/afinance') }}">FINANCE</a></li>
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
            <h1>BUDGET &amp; FINANCE</h1>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button class="btn-add-expense" onclick="openAddExpenseModal()">+ Add Expense</button>
                <button class="btn-add-budget" onclick="openAddBudgetModal()">+ Add Budget</button>
            </div>
        </div>

        <!-- ─── REPORT TABS ─── -->
        <div class="view-toggle" style="margin-bottom:20px;flex-wrap:wrap;">
            <button class="toggle-btn active report-tab" data-tab="expenses" onclick="switchReportTab('expenses')">Expenses</button>
            <button class="toggle-btn report-tab" data-tab="budgets" onclick="switchReportTab('budgets')">Budgets</button>
            <button class="toggle-btn report-tab" data-tab="expovrall" onclick="switchReportTab('expovrall')">EXPOVRALL</button>
            <button class="toggle-btn report-tab" data-tab="expdirect" onclick="switchReportTab('expdirect')">EXP DIRECT</button>
            <button class="toggle-btn report-tab" data-tab="adminexp" onclick="switchReportTab('adminexp')">ADMIN EXP</button>
            <button class="toggle-btn report-tab" data-tab="directexp" onclick="switchReportTab('directexp')">DIRECT EXP</button>
            <button class="toggle-btn report-tab" data-tab="overallexp" onclick="switchReportTab('overallexp')">OVERALL EXP</button>
            <button class="toggle-btn report-tab" data-tab="profit" onclick="switchReportTab('profit')">Profit/Loss</button>
            <button class="toggle-btn report-tab" data-tab="receivables" onclick="switchReportTab('receivables')">AR/AP</button>
            <button class="toggle-btn report-tab" data-tab="cash" onclick="switchReportTab('cash')">Cash Asset</button>
            <button class="toggle-btn report-tab" data-tab="repair" onclick="switchReportTab('repair')">Repair</button>
            <button class="toggle-btn report-tab" data-tab="backhoe" onclick="switchReportTab('backhoe')">Backhoe</button>
            <button class="toggle-btn report-tab" data-tab="bonds" onclick="switchReportTab('bonds')">Bonds</button>
            <button class="toggle-btn report-tab" data-tab="summary" onclick="switchReportTab('summary')">Summary</button>
        </div>

        <!-- ─── TAB 1: EXPENSES ─── -->
        <div id="tabExpenses" class="report-section active">
            <div class="stats-row expense-stats" id="expenseStats">
                <div class="stat-mini"><div class="stat-label">Total Budget</div><div class="stat-value blue" id="totalBudgetValue">₱0.00</div></div>
                <div class="stat-mini"><div class="stat-label">Total Expenses</div><div class="stat-value" id="totalExpensesValue" style="color:#1a2b3c;">₱0.00</div></div>
                <div class="stat-mini"><div class="stat-label">Net Variance</div><div class="stat-value red" id="netVarianceValue">₱0.00</div></div>
            </div>
            <div class="filter-tabs">
                <span class="tab" data-period="daily" onclick="setActiveTab(this,'daily')">Daily</span>
                <span class="tab" data-period="weekly" onclick="setActiveTab(this,'weekly')">Weekly</span>
                <span class="tab active" data-period="monthly" onclick="setActiveTab(this,'monthly')">Monthly</span>
                <span class="tab" data-period="yearly" onclick="setActiveTab(this,'yearly')">Yearly</span>
            </div>
            <div class="filter-row">
                <select id="projectFilter" class="project-filter" onchange="filterByProject()"><option value="all">All Projects</option></select>
                <input type="text" id="projectSearch" class="project-filter" placeholder="Search project name..." oninput="applyFilters()">
                <button class="btn-clear-search" onclick="clearSearch()">✕ Clear</button>
            </div>
            <div class="table-wrapper expense-table-wrapper">
                <table id="expenseTable">
                    <thead><tr><th>Project</th><th>Expense Description</th><th>Category</th><th>Amount</th><th>Date</th><th>Remarks</th></tr></thead>
                    <tbody id="expenseTableBody"></tbody>
                </table>
            </div>
            <div class="pagination-wrapper" id="expensePagination">
                <div class="rows-info"><span id="rowsInfoText">Showing 0 of 0 expenses</span>
                    <select id="financeRowsPerPage" onchange="changeFinancePageSize()">
                        <option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option>
                    </select>
                </div>
                <div class="pagination-links" id="financePaginationLinks"></div>
            </div>
        </div>

        <!-- ─── TAB 2: BUDGETS ─── -->
        <div id="tabBudgets" class="report-section">
            <div class="stats-row-budget budget-stats visible">
                <div class="stat-mini"><div class="stat-label">Total Budget</div><div class="stat-value blue" id="budgetTotalValue">₱0.00</div></div>
            </div>
            <div class="filter-row">
                <select id="budgetProjectFilter" onchange="filterBudgetTable()"><option value="all">All Projects</option></select>
                <input type="text" id="budgetSearch" placeholder="Search project name..." oninput="filterBudgetTable()">
                <button class="btn-clear-search" onclick="clearBudgetSearch()">✕ Clear</button>
            </div>
            <div class="budget-table-wrapper visible">
                <table id="budgetTable">
                    <thead><tr><th>Project Name</th><th>Budget Amount</th><th>Actual Spend</th><th>Remaining</th><th>Status</th></tr></thead>
                    <tbody id="budgetTableBody"><tr><td colspan="5" style="text-align:center;padding:20px;">Loading budget data...</td></tr></tbody>
                </table>
            </div>
            <div class="pagination-wrapper" id="budgetPagination">
                <div class="rows-info"><span id="budgetRowsInfo">Showing 0 of 0 projects</span>
                    <select id="budgetRowsPerPage" onchange="changeBudgetPageSize()">
                        <option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option>
                    </select>
                </div>
                <div class="pagination-links" id="budgetPaginationLinks"></div>
            </div>
        </div>

        <!-- ─── TAB 3: EXPOVRALL ─── -->
        <div id="tabExpovrall" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Month:
                    <input type="month" id="expovrallMonth" value="{{ date('Y-m') }}" onchange="loadExpovrall()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                </label>
                <button onclick="loadExpovrall()" style="padding:6px 16px;background:#1a2b3c;color:#fff;border:none;border-radius:6px;cursor:pointer;">Refresh</button>
            </div>
            <div class="report-table-wrapper">
                <table id="expovrallTable">
                    <thead><tr><th>Project</th><th>Const. Supply</th><th>Salaries & Wages</th><th>Permit, Taxes</th><th>Transport</th><th>Utilities</th><th>Delivery</th><th>Rent</th><th>Stationery</th><th>Depreciation</th><th>Repair & Maint</th><th>SSS/PhilHealth</th><th>Others</th><th>Total</th></tr></thead>
                    <tbody id="expovrallBody"><tr><td colspan="14" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- ─── TAB 4: EXP DIRECT ─── -->
        <div id="tabExpdirect" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Month:
                    <input type="month" id="expdirectMonth" value="{{ date('Y-m') }}" onchange="loadExpDirect()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                </label>
                <button onclick="loadExpDirect()" style="padding:6px 16px;background:#1a2b3c;color:#fff;border:none;border-radius:6px;cursor:pointer;">Refresh</button>
            </div>
            <div class="report-table-wrapper">
                <table id="expdirectTable">
                    <thead><tr><th>Project</th><th>Const. Supply</th><th>Salaries & Wages</th><th>Permit, Taxes</th><th>Transport</th><th>Utilities</th><th>Delivery</th><th>Others</th><th>Total</th><th>Admin Expense</th></tr></thead>
                    <tbody id="expdirectBody"><tr><td colspan="10" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- ─── TAB 5: ADMIN EXP ─── -->
        <div id="tabAdminexp" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Month:
                    <input type="month" id="adminexpMonth" value="{{ date('Y-m') }}" onchange="loadAdminExp()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                </label>
                <button onclick="loadAdminExp()" style="padding:6px 16px;background:#1a2b3c;color:#fff;border:none;border-radius:6px;cursor:pointer;">Refresh</button>
                <button onclick="openAddAdminExpModal()" class="btn-add-data">+ Add Admin Expense</button>
            </div>
            <div class="report-table-wrapper">
                <table id="adminexpTable">
                    <thead><tr><th>Project</th><th>Salaries & Wages</th><th>Permit, Taxes</th><th>Transport</th><th>Utilities</th><th>Delivery</th><th>Rent</th><th>Stationery</th><th>Depreciation</th><th>Repair & Maint</th><th>Misc</th><th>Penalty</th><th>SSS/PhilHealth</th><th>Others</th><th>Total</th></tr></thead>
                    <tbody id="adminexpBody"><tr><td colspan="15" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- ─── TAB 6: DIRECT EXP ─── -->
        <div id="tabDirectexp" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Month:
                    <input type="month" id="directexpMonth" value="{{ date('Y-m') }}" onchange="loadDirectExp()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                </label>
                <button onclick="loadDirectExp()" style="padding:6px 16px;background:#1a2b3c;color:#fff;border:none;border-radius:6px;cursor:pointer;">Refresh</button>
            </div>
            <div class="report-table-wrapper">
                <table id="directexpTable">
                    <thead><tr><th>Project</th><th>Const. Supply</th><th>Salaries & Wages</th><th>Permit, Taxes</th><th>Transport</th><th>Utilities</th><th>Delivery</th><th>Others</th><th>Total</th><th>Admin Expense</th></tr></thead>
                    <tbody id="directexpBody"><tr><td colspan="10" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- ─── TAB 7: OVERALL EXP ─── -->
        <div id="tabOverallexp" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Month:
                    <input type="month" id="overallexpMonth" value="{{ date('Y-m') }}" onchange="loadOverallExp()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                </label>
                <button onclick="loadOverallExp()" style="padding:6px 16px;background:#1a2b3c;color:#fff;border:none;border-radius:6px;cursor:pointer;">Refresh</button>
            </div>
            <div class="report-table-wrapper">
                <table id="overallexpTable">
                    <thead><tr><th>Project</th><th>Const. Supply</th><th>Salaries & Wages</th><th>Permit, Taxes</th><th>Transport</th><th>Utilities</th><th>Delivery</th><th>Others</th><th>Admin Expenses</th><th>Total</th></tr></thead>
                    <tbody id="overallexpBody"><tr><td colspan="10" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- ─── TAB 8: PROFIT/LOSS ─── -->
        <div id="tabProfit" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Report Type:
                    <select id="profitType" onchange="loadProfit()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                        <option value="direct">Direct Expenses</option>
                        <option value="overall">Overall Expenses</option>
                    </select>
                </label>
                <button onclick="loadProfit()" style="padding:6px 16px;background:#1a2b3c;color:#fff;border:none;border-radius:6px;cursor:pointer;">Refresh</button>
                <button onclick="openAddContractModal()" class="btn-add-data">+ Add Contract</button>
            </div>
            <div class="report-table-wrapper">
                <table id="profitTable">
                    <thead><tr><th>Project</th><th>Start Date</th><th>End Date</th><th>Contract Price</th><th>Addl. Works</th><th>Total Contract</th><th>Original Payment</th><th>Addl. Payment</th><th>Total Payment</th><th>Project Expense</th><th>Accounts Receivable</th><th>Profit/Loss (Payment)</th><th>Profit/Loss (Contract)</th></tr></thead>
                    <tbody id="profitBody"><tr><td colspan="13" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- ─── TAB 9: AR/AP ─── -->
        <div id="tabReceivables" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Type:
                    <select id="receivableType" onchange="loadReceivables()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                        <option value="accounts_receivable">Accounts Receivable</option>
                        <option value="accounts_payable">Accounts Payable</option>
                        <option value="cash_advance_site">Cash Advance (Site)</option>
                        <option value="advance_employee">Advances to Employees</option>
                    </select>
                </label>
                <button onclick="loadReceivables()" style="padding:6px 16px;background:#1a2b3c;color:#fff;border:none;border-radius:6px;cursor:pointer;">Refresh</button>
                <button onclick="openAddReceivableModal()" class="btn-add-data">+ Add Entry</button>
            </div>
            <div class="report-table-wrapper">
                <table id="receivableTable">
                    <thead><tr><th>Date</th><th>Counterparty</th><th>Project</th><th>30 Days</th><th>31-60 Days</th><th>61-90 Days</th><th>91-120 Days</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody id="receivableBody"><tr><td colspan="9" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- ─── TAB 10: CASH ASSET ─── -->
        <div id="tabCash" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Month:
                    <input type="month" id="cashMonth" value="{{ date('Y-m') }}" onchange="loadCashAsset()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                </label>
                <button onclick="loadCashAsset()" style="padding:6px 16px;background:#1a2b3c;color:#fff;border:none;border-radius:6px;cursor:pointer;">Refresh</button>
                <button onclick="openAddCashModal()" class="btn-add-data">+ Add Cash Position</button>
            </div>
            <div class="report-table-wrapper">
                <table id="cashTable">
                    <thead><tr><th>Account</th><th>Type</th><th>Balance</th></tr></thead>
                    <tbody id="cashBody"><tr><td colspan="3" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- ─── TAB 11: REPAIR ─── -->
        <div id="tabRepair" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Month:
                    <input type="month" id="repairMonth" value="{{ date('Y-m') }}" onchange="loadRepair()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                </label>
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Asset:
                    <select id="repairAsset" onchange="loadRepair()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                        <option value="">All Assets</option>
                    </select>
                </label>
                <button onclick="loadRepair()" style="padding:6px 16px;background:#1a2b3c;color:#fff;border:none;border-radius:6px;cursor:pointer;">Refresh</button>
                <button onclick="openAddRepairModal()" class="btn-add-data">+ Add Repair</button>
            </div>
            <div class="report-table-wrapper">
                <table id="repairTable">
                    <thead><tr><th>Asset</th><th>Expense Type</th><th>Amount</th><th>Date</th><th>Remarks</th></tr></thead>
                    <tbody id="repairBody"><tr><td colspan="5" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- ─── TAB 12: BACKHOE ─── -->
        <div id="tabBackhoe" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Asset:
                    <select id="backhoeAsset" onchange="loadBackhoe()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                        <option value="">All</option>
                    </select>
                </label>
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Month:
                    <input type="month" id="backhoeMonth" value="{{ date('Y-m') }}" onchange="loadBackhoe()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                </label>
                <button onclick="loadBackhoe()" style="padding:6px 16px;background:#1a2b3c;color:#fff;border:none;border-radius:6px;cursor:pointer;">Refresh</button>
                <button onclick="openAddBackhoeExpenseModal()" class="btn-add-data">+ Add Expense</button>
                <button onclick="openAddBackhoeRentalModal()" class="btn-add-data" style="background:#c9a96e;">+ Add Rental Income</button>
            </div>
            <div class="report-table-wrapper">
                <table id="backhoeTable">
                    <thead><tr><th>Asset</th><th>Period</th><th>Gas/Diesel</th><th>Payroll (Operator)</th><th>Repair</th><th>Other</th><th>Delivery</th><th>Transport</th><th>Total</th><th>Rental Income</th><th>Net Income</th></tr></thead>
                    <tbody id="backhoeBody"><tr><td colspan="11" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- ─── TAB 13: BONDS ─── -->
        <div id="tabBonds" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">
                    Project:
                    <select id="bondProjectFilter" onchange="loadBonds()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                        <option value="all">All Projects</option>
                    </select>
                </label>
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">
                    Status:
                    <select id="bondStatusFilter" onchange="loadBonds()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="released">Released</option>
                        <option value="forfeited">Forfeited</option>
                    </select>
                </label>
                <button onclick="loadBonds()" style="padding:6px 16px;background:#1a2b3c;color:#fff;border:none;border-radius:6px;cursor:pointer;">Refresh</button>
                <button onclick="openAddBondModal()" style="padding:6px 16px;background:#c9a96e;color:#fff;border:none;border-radius:6px;cursor:pointer;">+ Add Bond</button>
            </div>
            <div class="report-table-wrapper">
                <table id="bondTable">
                    <thead><tr><th>Date</th><th>Project</th><th>Provider</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody id="bondBody"><tr><td colspan="6" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- ─── TAB 14: SUMMARY ─── -->
        <div id="tabSummary" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Month:
                    <input type="month" id="summaryMonth" value="{{ date('Y-m') }}" onchange="loadSummary()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                </label>
                <button onclick="loadSummary()" style="padding:6px 16px;background:#1a2b3c;color:#fff;border:none;border-radius:6px;cursor:pointer;">Refresh</button>
            </div>
            <div class="report-table-wrapper">
                <table id="summaryTable">
                    <thead><tr><th>Category</th><th>Amount</th></tr></thead>
                    <tbody id="summaryBody"><tr><td colspan="2" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- ─── ADD EXPENSE MODAL ─── -->
    <div id="addExpenseModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header"><h2>Add Expense</h2><button class="modal-close" onclick="closeAddExpenseModal()">×</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Project <span class="required">*</span></label><select id="expenseProject"><option value="">Select Project...</option></select></div>
                <div class="form-group"><label>Expense Description <span class="required">*</span></label><input type="text" placeholder="e.g. Salary" id="expenseDesc"></div>
                <div class="form-group"><label>Category <span class="required">*</span></label><select id="expenseCategory"><option value="">Select Category...</option></select></div>
                <div class="form-group"><label>Amount <span class="required">*</span></label><input type="number" step="0.01" placeholder="0.00" id="expenseAmount"></div>
                <div class="form-group"><label>Date <span class="required">*</span></label><input type="date" id="expenseDate" value="{{ date('Y-m-d') }}"></div>
                <div class="form-group"><label>Remarks</label><input type="text" placeholder="Additional notes..." id="expenseRemarks"></div>
                <div class="form-group">
                    <label>Expense Proof</label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="expenseProofFile" accept=".jpg,.jpeg,.png,.pdf" style="display: none;">
                        <label for="expenseProofFile" class="file-upload-button">Choose File</label>
                        <span id="expenseProofFileName" class="file-upload-name">No file chosen</span>
                    </div>
                    <span class="file-upload-hint">PDF, JPG, or PNG • Maximum 5MB</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddExpenseModal()">Cancel</button>
                <button class="btn-save" onclick="saveExpense()">Add Expense</button>
            </div>
        </div>
    </div>

    <!-- ─── ADD BUDGET MODAL ─── -->
    <div id="addBudgetModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header"><h2>Add Budget</h2><button class="modal-close" onclick="closeAddBudgetModal()">×</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Project <span class="required">*</span></label><select id="budgetProject"><option value="">Select Project...</option></select></div>
                <div class="form-group"><label>Budget Amount <span class="required">*</span></label><input type="number" step="0.01" placeholder="0.00" id="budgetAmount"></div>
                <div class="form-group">
                    <label>Budget Proof</label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="budgetProofFile" accept=".jpg,.jpeg,.png,.pdf" style="display: none;">
                        <label for="budgetProofFile" class="file-upload-button">Choose File</label>
                        <span id="budgetProofFileName" class="file-upload-name">No file chosen</span>
                    </div>
                    <span class="file-upload-hint">PDF, JPG, or PNG • Maximum 5MB</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddBudgetModal()">Cancel</button>
                <button class="btn-save" onclick="saveBudget()">Add Budget</button>
            </div>
        </div>
    </div>

    <!-- ─── ADD ADMIN EXPENSE MODAL ─── -->
    <div id="addAdminExpModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header"><h2>Add Admin Expense</h2><button class="modal-close" onclick="closeAddAdminExpModal()">×</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Project <span class="required">*</span></label><select id="adminExpProject"><option value="">Select Project...</option></select></div>
                <div class="form-group"><label>Category <span class="required">*</span></label>
                    <select id="adminExpCategory">
                        <option value="">Select Category...</option>
                        <option value="RENT">Rent Expense</option>
                        <option value="STATIONERY">Stationery Expense</option>
                        <option value="DEPRECIATION">Depreciation Expense</option>
                        <option value="REPAIR_MAINT">Repair & Maintenance</option>
                        <option value="MISC">Miscellaneous Expense</option>
                        <option value="PENALTY">Penalty Expense</option>
                        <option value="SSS_PHILHEALTH">SSS, PhilHealth & Employer Contributions</option>
                    </select>
                </div>
                <div class="form-group"><label>Amount <span class="required">*</span></label><input type="number" step="0.01" placeholder="0.00" id="adminExpAmount"></div>
                <div class="form-group"><label>Date <span class="required">*</span></label><input type="date" id="adminExpDate" value="{{ date('Y-m-d') }}"></div>
                <div class="form-group"><label>Remarks</label><input type="text" placeholder="Additional notes..." id="adminExpRemarks"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddAdminExpModal()">Cancel</button>
                <button class="btn-save" onclick="saveAdminExp()">Add Admin Expense</button>
            </div>
        </div>
    </div>

    <!-- ─── ADD CONTRACT MODAL ─── -->
    <div id="addContractModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header"><h2>Add/Edit Contract</h2><button class="modal-close" onclick="closeAddContractModal()">×</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Project <span class="required">*</span></label><select id="contractProject"><option value="">Select Project...</option></select></div>
                <div class="form-group"><label>Contract Price <span class="required">*</span></label><input type="number" step="0.01" placeholder="0.00" id="contractPrice"></div>
                <div class="form-group"><label>Additional Works (Contract)</label><input type="number" step="0.01" placeholder="0.00" id="contractAddlWorks"></div>
                <div class="form-group"><label>Payment Received</label><input type="number" step="0.01" placeholder="0.00" id="contractPayment"></div>
                <div class="form-group"><label>Additional Works (Payment)</label><input type="number" step="0.01" placeholder="0.00" id="contractAddlPayment"></div>
                <div class="form-group"><label>Remarks</label><input type="text" placeholder="Additional notes..." id="contractRemarks"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddContractModal()">Cancel</button>
                <button class="btn-save" onclick="saveContract()">Save Contract</button>
            </div>
        </div>
    </div>

    <!-- ─── ADD RECEIVABLE MODAL ─── -->
    <div id="addReceivableModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header"><h2>Add AR/AP Entry</h2><button class="modal-close" onclick="closeAddReceivableModal()">×</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Entry Type <span class="required">*</span></label>
                    <select id="receivableEntryType">
                        <option value="accounts_receivable">Accounts Receivable</option>
                        <option value="accounts_payable">Accounts Payable</option>
                        <option value="cash_advance_site">Cash Advance (Site)</option>
                        <option value="advance_employee">Advances to Employees</option>
                    </select>
                </div>
                <div class="form-group"><label>Counterparty Name <span class="required">*</span></label><input type="text" placeholder="Client/Vendor/Employee name" id="receivableCounterparty"></div>
                <div class="form-group"><label>Project</label><select id="receivableProject"><option value="">Select Project...</option></select></div>
                <div class="form-group"><label>Date <span class="required">*</span></label><input type="date" id="receivableDate" value="{{ date('Y-m-d') }}"></div>
                <div class="form-row">
                    <div class="form-group"><label>30 Days</label><input type="number" step="0.01" placeholder="0.00" id="receivable30d"></div>
                    <div class="form-group"><label>31-60 Days</label><input type="number" step="0.01" placeholder="0.00" id="receivable60d"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>61-90 Days</label><input type="number" step="0.01" placeholder="0.00" id="receivable90d"></div>
                    <div class="form-group"><label>91-120 Days</label><input type="number" step="0.01" placeholder="0.00" id="receivable120d"></div>
                </div>
                <div class="form-group"><label>Status</label>
                    <select id="receivableStatus">
                        <option value="outstanding">Outstanding</option>
                        <option value="settled">Settled</option>
                    </select>
                </div>
                <div class="form-group"><label>Remarks</label><input type="text" placeholder="Additional notes..." id="receivableRemarks"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddReceivableModal()">Cancel</button>
                <button class="btn-save" onclick="saveReceivable()">Add Entry</button>
            </div>
        </div>
    </div>

    <!-- ─── ADD CASH POSITION MODAL ─── -->
    <div id="addCashModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header"><h2>Add Cash Position</h2><button class="modal-close" onclick="closeAddCashModal()">×</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Account <span class="required">*</span></label>
                    <select id="cashAccount">
                        <option value="">Select Account...</option>
                    </select>
                </div>
                <div class="form-group"><label>Month <span class="required">*</span></label><input type="month" id="cashPeriod" value="{{ date('Y-m') }}"></div>
                <div class="form-group"><label>Balance <span class="required">*</span></label><input type="number" step="0.01" placeholder="0.00" id="cashBalance"></div>
                <div class="form-group"><label>Remarks</label><input type="text" placeholder="Additional notes..." id="cashRemarks"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddCashModal()">Cancel</button>
                <button class="btn-save" onclick="saveCashPosition()">Add Cash Position</button>
            </div>
        </div>
    </div>

    <!-- ─── ADD REPAIR MODAL ─── -->
    <div id="addRepairModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header"><h2>Add Repair/Maintenance</h2><button class="modal-close" onclick="closeAddRepairModal()">×</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Asset <span class="required">*</span></label>
                    <select id="repairAssetSelect">
                        <option value="">Select Asset...</option>
                    </select>
                </div>
                <div class="form-group"><label>Expense Type <span class="required">*</span></label>
                    <select id="repairExpenseType">
                        <option value="repair">Repair</option>
                        <option value="gas_diesel">Gas/Diesel</option>
                        <option value="payroll_operator">Payroll (Operator)</option>
                        <option value="delivery">Delivery</option>
                        <option value="transportation">Transportation</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group"><label>Amount <span class="required">*</span></label><input type="number" step="0.01" placeholder="0.00" id="repairAmount"></div>
                <div class="form-group"><label>Date <span class="required">*</span></label><input type="date" id="repairDate" value="{{ date('Y-m-d') }}"></div>
                <div class="form-group"><label>Remarks</label><input type="text" placeholder="Additional notes..." id="repairRemarks"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddRepairModal()">Cancel</button>
                <button class="btn-save" onclick="saveRepair()">Add Repair</button>
            </div>
        </div>
    </div>

    <!-- ─── ADD BACKHOE EXPENSE MODAL ─── -->
    <div id="addBackhoeExpenseModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header"><h2>Add Backhoe Expense</h2><button class="modal-close" onclick="closeAddBackhoeExpenseModal()">×</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Asset <span class="required">*</span></label>
                    <select id="backhoeExpenseAsset">
                        <option value="">Select Asset...</option>
                    </select>
                </div>
                <div class="form-group"><label>Project Site</label><select id="backhoeExpenseProject"><option value="">Select Project...</option></select></div>
                <div class="form-group"><label>Expense Type <span class="required">*</span></label>
                    <select id="backhoeExpenseType">
                        <option value="gas_diesel">Gas/Diesel</option>
                        <option value="payroll_operator">Payroll (Operator)</option>
                        <option value="repair">Repair</option>
                        <option value="other">Other</option>
                        <option value="delivery">Delivery</option>
                        <option value="transportation">Transportation</option>
                    </select>
                </div>
                <div class="form-group"><label>Amount <span class="required">*</span></label><input type="number" step="0.01" placeholder="0.00" id="backhoeExpenseAmount"></div>
                <div class="form-group"><label>Date <span class="required">*</span></label><input type="date" id="backhoeExpenseDate" value="{{ date('Y-m-d') }}"></div>
                <div class="form-group"><label>Remarks</label><input type="text" placeholder="Additional notes..." id="backhoeExpenseRemarks"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddBackhoeExpenseModal()">Cancel</button>
                <button class="btn-save" onclick="saveBackhoeExpense()">Add Expense</button>
            </div>
        </div>
    </div>

    <!-- ─── ADD BACKHOE RENTAL MODAL ─── -->
    <div id="addBackhoeRentalModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header"><h2>Add Backhoe Rental Income</h2><button class="modal-close" onclick="closeAddBackhoeRentalModal()">×</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Asset <span class="required">*</span></label>
                    <select id="backhoeRentalAsset">
                        <option value="">Select Asset...</option>
                    </select>
                </div>
                <div class="form-group"><label>Project Site</label><select id="backhoeRentalProject"><option value="">Select Project...</option></select></div>
                <div class="form-group"><label>Period <span class="required">*</span></label><input type="month" id="backhoeRentalPeriod" value="{{ date('Y-m') }}"></div>
                <div class="form-group"><label>Amount <span class="required">*</span></label><input type="number" step="0.01" placeholder="0.00" id="backhoeRentalAmount"></div>
                <div class="form-group"><label>Remarks</label><input type="text" placeholder="Additional notes..." id="backhoeRentalRemarks"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddBackhoeRentalModal()">Cancel</button>
                <button class="btn-save" onclick="saveBackhoeRental()">Add Rental Income</button>
            </div>
        </div>
    </div>

    <!-- ─── ADD BOND MODAL ─── -->
    <div id="addBondModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header"><h2>Add Construction Bond</h2><button class="modal-close" onclick="closeAddBondModal()">×</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Project <span class="required">*</span></label><select id="bondProject"><option value="">Select Project...</option></select></div>
                <div class="form-group"><label>Date <span class="required">*</span></label><input type="date" id="bondDate" value="{{ date('Y-m-d') }}"></div>
                <div class="form-group"><label>Amount <span class="required">*</span></label><input type="number" step="0.01" placeholder="0.00" id="bondAmount"></div>
                <div class="form-group"><label>Provider</label><input type="text" placeholder="Bond provider..." id="bondProvider"></div>
                <div class="form-group"><label>Status</label><select id="bondStatus"><option value="active">Active</option><option value="released">Released</option><option value="forfeited">Forfeited</option></select></div>
                <div class="form-group"><label>Remarks</label><input type="text" placeholder="Additional notes..." id="bondRemarks"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddBondModal()">Cancel</button>
                <button class="btn-save" onclick="saveBond()">Add Bond</button>
            </div>
        </div>
    </div>

    <!-- ─── EXPENSE DETAIL MODAL ─── -->
    <div id="expenseDetailModal" class="modal-overlay modal-update">
        <div class="modal-container" style="width:700px;max-width:95%;">
            <div class="modal-header"><div><h2 id="detailModalTitle">Expense Details</h2></div><button class="modal-close" onclick="closeExpenseDetailModal()">×</button></div>
            <div class="detail-grid">
                <div class="detail-item"><label>Project</label><span id="detailProjectDisplay" class="detail-value">—</span><select id="detailProjectEdit" class="detail-edit" style="display:none;"></select></div>
                <div class="detail-item"><label>Expense Description</label><span id="detailDescDisplay" class="detail-value">—</span><input type="text" id="detailDescEdit" class="detail-edit" style="display:none;"></div>
                <div class="detail-item"><label>Category</label><span id="detailCategoryDisplay" class="detail-value">—</span><select id="detailCategoryEdit" class="detail-edit" style="display:none;"></select></div>
                <div class="detail-item"><label>Amount</label><span id="detailAmountDisplay" class="detail-value">—</span><input type="number" step="0.01" id="detailAmountEdit" class="detail-edit" style="display:none;"></div>
                <div class="detail-item"><label>Date</label><span id="detailDateDisplay" class="detail-value">—</span><input type="date" id="detailDateEdit" class="detail-edit" style="display:none;"></div>
                <div class="detail-item"><label>Remarks</label><span id="detailRemarksDisplay" class="detail-value">—</span><input type="text" id="detailRemarksEdit" class="detail-edit" style="display:none;"></div>
                <div class="expense-file-section">
                    <label>Supporting File</label>
                    <div id="expenseFileDisplay" class="expense-file-display">
                        <div class="expense-file-info">
                            <span id="expenseFileIcon" class="expense-file-icon">📎</span>
                            <span id="expenseFileNameDisplay" class="expense-file-name">No file attached</span>
                        </div>
                        <div class="expense-file-actions">
                            <button type="button" id="viewExpenseFileBtn" class="btn-view-file" style="display: none;" onclick="viewExpenseFile()">View File</button>
                            <label for="detailExpenseFile" id="changeExpenseFileBtn" class="btn-change-file" style="display: none;">Change File</label>
                            <button type="button" id="deleteExpenseFileBtn" class="btn-delete-file" style="display: none;" onclick="deleteExpenseFile()">Delete File</button>
                        </div>
                    </div>
                    <input type="file" id="detailExpenseFile" class="file-input-hidden" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" onchange="handleDetailFileSelect(this)">
                    <div id="filePreviewContainer" class="file-preview-container">
                        <img id="filePreviewImage" src="" alt="File Preview">
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="justify-content:flex-end;gap:12px;">
                <button class="btn-cancel" onclick="closeExpenseDetailModal()">Close</button>
                <button class="btn-delete" id="detailDeleteBtn" onclick="deleteExpense()">Delete</button>
                <button class="btn-edit-project" id="detailEditBtn" onclick="toggleDetailEdit()">Edit</button>
                <button class="btn-save" id="detailSaveBtn" style="display:none;" onclick="saveDetailChanges()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- ─── BUDGET DETAIL MODAL ─── -->
    <div id="budgetDetailModal" class="modal-overlay modal-update">
        <div class="modal-container" style="width:600px;max-width:95%;">
            <div class="modal-header"><div><h2 id="budgetDetailModalTitle">Budget Details</h2></div><button class="modal-close" onclick="closeBudgetDetailModal()">×</button></div>
            <div class="budget-detail-grid">
                <div class="budget-detail-item"><label>Project</label><span id="budgetDetailProjectDisplay" class="budget-detail-value">—</span></div>
                <div class="budget-detail-item"><label>Budget Amount</label><span id="budgetDetailAmountDisplay" class="budget-detail-value">—</span><input type="number" step="0.01" id="budgetDetailAmountEdit" class="budget-detail-edit" style="display:none;"></div>
                <div class="budget-detail-item"><label>Actual Spend</label><span id="budgetDetailActualDisplay" class="budget-detail-value">—</span></div>
                <div class="budget-detail-item"><label>Remaining</label><span id="budgetDetailRemainingDisplay" class="budget-detail-value">—</span></div>
                <div class="expense-file-section">
                    <label>Supporting File</label>
                    <div id="budgetFileDisplay" class="expense-file-display">
                        <div class="expense-file-info">
                            <span id="budgetFileIcon" class="expense-file-icon">📎</span>
                            <span id="budgetFileNameDisplay" class="expense-file-name">No file attached</span>
                        </div>
                        <div class="expense-file-actions">
                            <button type="button" id="viewBudgetFileBtn" class="btn-view-file" style="display: none;" onclick="viewBudgetFile()">View File</button>
                            <label for="detailBudgetFile" id="changeBudgetFileBtn" class="btn-change-file" style="display: none;">Change File</label>
                            <button type="button" id="deleteBudgetFileBtn" class="btn-delete-file" style="display: none;" onclick="deleteBudgetFile()">Delete File</button>
                        </div>
                    </div>
                    <input type="file" id="detailBudgetFile" class="file-input-hidden" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" onchange="handleBudgetDetailFileSelect(this)">
                    <div id="budgetFilePreviewContainer" class="file-preview-container">
                        <img id="budgetFilePreviewImage" src="" alt="File Preview">
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="justify-content:flex-end;gap:12px;">
                <button class="btn-cancel" onclick="closeBudgetDetailModal()">Close</button>
                <button class="btn-delete" id="budgetDetailDeleteBtn" onclick="deleteBudget()">Delete</button>
                <button class="btn-edit-project" id="budgetDetailEditBtn" onclick="toggleBudgetDetailEdit()">Edit</button>
                <button class="btn-save" id="budgetDetailSaveBtn" style="display:none;" onclick="saveBudgetDetailChanges()">Save Changes</button>
            </div>
        </div>
    </div>

    <script>
        // ─── STATE VARIABLES ───────────────────────────────────────────
        var financeProjects = [];
        var financeCategories = [];
        var financeExpenses = [];
        var financeFilteredData = [];
        var financePageSize = 25;
        var financeCurrentPage = 1;
        var currentDetailRow = null;
        var isEditMode = false;
        var currentReportTab = 'expenses';

        var budgetData = [];
        var budgetFilteredData = [];
        var budgetPageSize = 25;
        var budgetCurrentPage = 1;
        var budgetProjectFilter = 'all';
        var budgetSearchTerm = '';
        var currentBudgetRow = null;
        var isBudgetEditMode = false;

        var currentPeriod = 'monthly';
        var currentSearchTerm = '';
        var currentProjectFilter = 'all';

        var deleteCallback = null;
        var budgetDeleteCallback = null;
        var bondDeleteCallback = null;
        var errorTimeout = null;
        var successTimeout = null;

        var API_BASE = '/api/finance';
        var assets = [];

        // ─── FILE UPLOAD UI STATE ─────────────────────────────────────
        var selectedDetailFile = null;
        var currentExpenseFileUrl = null;
        var expenseFileMarkedForRemoval = false;
        var selectedBudgetDetailFile = null;
        var currentBudgetFileUrl = null;
        var budgetFileMarkedForRemoval = false;

        // ─── CATEGORY MAPPINGS ─────────────────────────────────────────
        var EXPOVRALL_CATEGORIES = ['CONST_SUPPLY', 'SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'RENT', 'STATIONERY', 'DEPRECIATION', 'REPAIR_MAINT', 'SSS_PHILHEALTH', 'OTHERS'];
        var EXP_DIRECT_CATEGORIES = ['CONST_SUPPLY', 'SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'OTHERS'];
        var ADMIN_EXP_CATEGORIES = ['SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'RENT', 'STATIONERY', 'DEPRECIATION', 'REPAIR_MAINT', 'MISC', 'PENALTY', 'SSS_PHILHEALTH', 'OTHERS'];

        // ─── UTILITY FUNCTIONS ─────────────────────────────────────────
        function hideBadge(event) { var badge = document.getElementById('notifBadge'); if (badge) badge.style.display = 'none'; }

        function showError(message) {
            var notif = document.getElementById('errorNotification');
            var msgSpan = document.getElementById('errorMessage');
            if (msgSpan) msgSpan.textContent = message || 'An error occurred. Please try again.';
            notif.style.display = 'block';
            if (errorTimeout) clearTimeout(errorTimeout);
            errorTimeout = setTimeout(closeError, 5000);
        }

        function closeError() {
            document.getElementById('errorNotification').style.display = 'none';
            if (errorTimeout) { clearTimeout(errorTimeout); errorTimeout = null; }
        }

        function showSuccess(message) {
            var notif = document.getElementById('successNotification');
            var msgSpan = document.getElementById('successMessage');
            if (msgSpan) msgSpan.textContent = message || 'Saved successfully!';
            notif.style.display = 'block';
            if (successTimeout) clearTimeout(successTimeout);
            successTimeout = setTimeout(closeSuccess, 5000);
        }

        function closeSuccess() {
            document.getElementById('successNotification').style.display = 'none';
            if (successTimeout) { clearTimeout(successTimeout); successTimeout = null; }
        }

        function formatCurrency(value) {
            var amount = parseFloat(value) || 0;
            return '₱' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function handleDetailFileSelect(input) {
            var fileNameDisplay = document.getElementById('expenseFileNameDisplay');
            var fileIcon = document.getElementById('expenseFileIcon');
            var viewButton = document.getElementById('viewExpenseFileBtn');
            var deleteButton = document.getElementById('deleteExpenseFileBtn');
            var previewContainer = document.getElementById('filePreviewContainer');
            var previewImage = document.getElementById('filePreviewImage');

            if (!input.files || !input.files.length) {
                selectedDetailFile = null;
                fileNameDisplay.textContent = 'No file attached';
                fileIcon.textContent = '📎';
                viewButton.style.display = 'none';
                deleteButton.style.display = 'none';
                previewContainer.style.display = 'none';
                return;
            }

            selectedDetailFile = input.files[0];
            expenseFileMarkedForRemoval = false;
            fileNameDisplay.textContent = selectedDetailFile.name;

            var fileType = selectedDetailFile.type || '';
            if (fileType.startsWith('image/')) {
                fileIcon.textContent = '🖼️';
                if (currentExpenseFileUrl) { URL.revokeObjectURL(currentExpenseFileUrl); }
                currentExpenseFileUrl = URL.createObjectURL(selectedDetailFile);
                previewImage.src = currentExpenseFileUrl;
                previewContainer.style.display = 'block';
            } else if (fileType === 'application/pdf') {
                fileIcon.textContent = '📄';
                previewContainer.style.display = 'none';
            } else {
                fileIcon.textContent = '📎';
                previewContainer.style.display = 'none';
            }

            viewButton.style.display = 'inline-block';
            deleteButton.style.display = 'inline-block';
        }

        function viewExpenseFile() {
            if (selectedDetailFile) {
                var fileUrl = URL.createObjectURL(selectedDetailFile);
                window.open(fileUrl, '_blank');
                setTimeout(function() { URL.revokeObjectURL(fileUrl); }, 60000);
                return;
            }
            var viewButton = document.getElementById('viewExpenseFileBtn');
            var savedUrl = viewButton ? viewButton.getAttribute('data-file-url') : null;
            if (savedUrl) { window.open(savedUrl, '_blank'); return; }
            showError('No file is currently attached.');
        }

        function deleteExpenseFile() {
            selectedDetailFile = null;
            expenseFileMarkedForRemoval = true;
            var detailFileInput = document.getElementById('detailExpenseFile');
            if (detailFileInput) detailFileInput.value = '';

            var fileNameDisplay = document.getElementById('expenseFileNameDisplay');
            var fileIcon = document.getElementById('expenseFileIcon');
            var viewButton = document.getElementById('viewExpenseFileBtn');
            var deleteButton = document.getElementById('deleteExpenseFileBtn');
            var previewContainer = document.getElementById('filePreviewContainer');

            if (fileNameDisplay) fileNameDisplay.textContent = 'No file attached';
            if (fileIcon) fileIcon.textContent = '📎';
            if (viewButton) viewButton.style.display = 'none';
            if (deleteButton) deleteButton.style.display = 'none';
            if (previewContainer) previewContainer.style.display = 'none';

            if (currentExpenseFileUrl) { URL.revokeObjectURL(currentExpenseFileUrl); currentExpenseFileUrl = null; }
        }

        function handleBudgetDetailFileSelect(input) {
            var fileNameDisplay = document.getElementById('budgetFileNameDisplay');
            var fileIcon = document.getElementById('budgetFileIcon');
            var viewButton = document.getElementById('viewBudgetFileBtn');
            var deleteButton = document.getElementById('deleteBudgetFileBtn');
            var previewContainer = document.getElementById('budgetFilePreviewContainer');
            var previewImage = document.getElementById('budgetFilePreviewImage');

            if (!input.files || !input.files.length) {
                selectedBudgetDetailFile = null;
                fileNameDisplay.textContent = 'No file attached';
                fileIcon.textContent = '📎';
                viewButton.style.display = 'none';
                deleteButton.style.display = 'none';
                previewContainer.style.display = 'none';
                return;
            }

            selectedBudgetDetailFile = input.files[0];
            budgetFileMarkedForRemoval = false;
            fileNameDisplay.textContent = selectedBudgetDetailFile.name;

            var fileType = selectedBudgetDetailFile.type || '';
            if (fileType.startsWith('image/')) {
                fileIcon.textContent = '🖼️';
                if (currentBudgetFileUrl) { URL.revokeObjectURL(currentBudgetFileUrl); }
                currentBudgetFileUrl = URL.createObjectURL(selectedBudgetDetailFile);
                previewImage.src = currentBudgetFileUrl;
                previewContainer.style.display = 'block';
            } else if (fileType === 'application/pdf') {
                fileIcon.textContent = '📄';
                previewContainer.style.display = 'none';
            } else {
                fileIcon.textContent = '📎';
                previewContainer.style.display = 'none';
            }

            viewButton.style.display = 'inline-block';
            deleteButton.style.display = 'inline-block';
        }

        function viewBudgetFile() {
            if (selectedBudgetDetailFile) {
                var fileUrl = URL.createObjectURL(selectedBudgetDetailFile);
                window.open(fileUrl, '_blank');
                setTimeout(function() { URL.revokeObjectURL(fileUrl); }, 60000);
                return;
            }
            var viewButton = document.getElementById('viewBudgetFileBtn');
            var savedUrl = viewButton ? viewButton.getAttribute('data-file-url') : null;
            if (savedUrl) { window.open(savedUrl, '_blank'); return; }
            showError('No file is currently attached.');
        }

        function deleteBudgetFile() {
            selectedBudgetDetailFile = null;
            budgetFileMarkedForRemoval = true;
            var detailFileInput = document.getElementById('detailBudgetFile');
            if (detailFileInput) detailFileInput.value = '';

            var fileNameDisplay = document.getElementById('budgetFileNameDisplay');
            var fileIcon = document.getElementById('budgetFileIcon');
            var viewButton = document.getElementById('viewBudgetFileBtn');
            var deleteButton = document.getElementById('deleteBudgetFileBtn');
            var previewContainer = document.getElementById('budgetFilePreviewContainer');

            if (fileNameDisplay) fileNameDisplay.textContent = 'No file attached';
            if (fileIcon) fileIcon.textContent = '📎';
            if (viewButton) viewButton.style.display = 'none';
            if (deleteButton) deleteButton.style.display = 'none';
            if (previewContainer) previewContainer.style.display = 'none';

            if (currentBudgetFileUrl) { URL.revokeObjectURL(currentBudgetFileUrl); currentBudgetFileUrl = null; }
        }

        function initializeProofFileUpload() {
            var expenseProofFile = document.getElementById('expenseProofFile');
            var expenseProofFileName = document.getElementById('expenseProofFileName');
            if (expenseProofFile) {
                expenseProofFile.addEventListener('change', function() {
                    expenseProofFileName.textContent = (this.files && this.files.length > 0) ? this.files[0].name : 'No file chosen';
                });
            }
            var budgetProofFile = document.getElementById('budgetProofFile');
            var budgetProofFileName = document.getElementById('budgetProofFileName');
            if (budgetProofFile) {
                budgetProofFile.addEventListener('change', function() {
                    budgetProofFileName.textContent = (this.files && this.files.length > 0) ? this.files[0].name : 'No file chosen';
                });
            }
        }

        function apiFetch(endpoint, options) {
            return fetch(API_BASE + endpoint, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    ...(options?.headers || {})
                },
                credentials: 'same-origin'
            }).then(function(response) {
                if (!response.ok) {
                    return response.text().then(function(text) {
                        try {
                            var data = JSON.parse(text);
                            throw new Error(data.message || data.errors ? Object.values(data.errors).flat().join(', ') : 'Server error');
                        } catch (e) {
                            throw new Error('Server error: ' + text.substring(0, 100));
                        }
                    });
                }
                return response.json();
            });
        }

        // ─── TAB SWITCHING ─────────────────────────────────────────────
        function switchReportTab(tab) {
            currentReportTab = tab;
            document.querySelectorAll('.report-tab').forEach(function(el) {
                el.classList.toggle('active', el.dataset.tab === tab);
            });
            document.querySelectorAll('.report-section').forEach(function(el) {
                el.classList.toggle('active', el.id === 'tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
            });

            switch(tab) {
                case 'expenses': applyFilters(); break;
                case 'budgets': fetchBudgetData(); break;
                case 'expovrall': loadExpovrall(); break;
                case 'expdirect': loadExpDirect(); break;
                case 'adminexp': loadAdminExp(); break;
                case 'directexp': loadDirectExp(); break;
                case 'overallexp': loadOverallExp(); break;
                case 'profit': loadProfit(); break;
                case 'receivables': loadReceivables(); break;
                case 'cash': loadCashAsset(); break;
                case 'repair': loadRepair(); break;
                case 'backhoe': loadBackhoe(); break;
                case 'bonds': loadBonds(); break;
                case 'summary': loadSummary(); break;
            }
        }

        // ─── DELETE MODALS ─────────────────────────────────────────────
        function openDeleteModal(message, callback) {
            document.getElementById('deleteConfirmMessage').textContent = message || 'Are you sure you want to permanently delete this item?';
            deleteCallback = callback;
            document.getElementById('deleteConfirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
            document.body.style.overflow = '';
            deleteCallback = null;
        }

        function confirmDelete() {
            if (typeof deleteCallback === 'function') { deleteCallback(); }
            closeDeleteModal();
        }

        document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });

        function openBudgetDeleteModal(message, callback) {
            document.getElementById('deleteBudgetConfirmMessage').textContent = message || 'Are you sure you want to permanently delete this budget?';
            budgetDeleteCallback = callback;
            document.getElementById('deleteBudgetConfirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeBudgetDeleteModal() {
            document.getElementById('deleteBudgetConfirmModal').style.display = 'none';
            document.body.style.overflow = '';
            budgetDeleteCallback = null;
        }

        function confirmBudgetDelete() {
            if (typeof budgetDeleteCallback === 'function') { budgetDeleteCallback(); }
            closeBudgetDeleteModal();
        }

        document.getElementById('deleteBudgetConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) closeBudgetDeleteModal();
        });

        function openBondDeleteModal(message, callback) {
            document.getElementById('deleteBondConfirmMessage').textContent = message || 'Are you sure you want to permanently delete this bond?';
            bondDeleteCallback = callback;
            document.getElementById('deleteBondConfirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeBondDeleteModal() {
            document.getElementById('deleteBondConfirmModal').style.display = 'none';
            document.body.style.overflow = '';
            bondDeleteCallback = null;
        }

        function confirmBondDelete() {
            if (typeof bondDeleteCallback === 'function') { bondDeleteCallback(); }
            closeBondDeleteModal();
        }

        document.getElementById('deleteBondConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) closeBondDeleteModal();
        });

        // ─── EXPENSE FILTERS ───────────────────────────────────────────
        function setActiveTab(el, period) {
            document.querySelectorAll('.filter-tabs .tab').forEach(function(tab) { tab.classList.remove('active'); });
            el.classList.add('active');
            currentPeriod = period;
            applyFilters();
        }

        function filterByProject() {
            currentProjectFilter = document.getElementById('projectFilter').value;
            applyFilters();
        }

        function clearSearch() {
            document.getElementById('projectSearch').value = '';
            currentSearchTerm = '';
            applyFilters();
        }

        function applyFilters() {
            if (currentReportTab !== 'expenses') return;

            var searchTerm = document.getElementById('projectSearch').value.toLowerCase().trim();
            currentSearchTerm = searchTerm;

            var projectFiltered = currentProjectFilter === 'all'
                ? financeExpenses
                : financeExpenses.filter(function(expense) { return expense.project_name === currentProjectFilter; });

            var searchFiltered = projectFiltered;
            if (searchTerm) {
                searchFiltered = projectFiltered.filter(function(expense) {
                    var projectName = (expense.project_name || '').toLowerCase();
                    var description = (expense.expense_description || '').toLowerCase();
                    var category = (expense.category_name || '').toLowerCase();
                    var remarks = (expense.remarks || '').toLowerCase();
                    return projectName.includes(searchTerm) || description.includes(searchTerm) || category.includes(searchTerm) || remarks.includes(searchTerm);
                });
            }

            financeFilteredData = filterByPeriod(searchFiltered);
            renderFinancePage(1);
            updateFinanceTotals();
        }

        function filterByPeriod(expenses) {
            var now = new Date();
            return expenses.filter(function(expense) {
                if (!expense.expense_date) return false;
                var expenseDate = new Date(expense.expense_date);
                switch(currentPeriod) {
                    case 'daily': return expenseDate.toDateString() === now.toDateString();
                    case 'weekly':
                        var weekStart = new Date(now); weekStart.setDate(now.getDate() - now.getDay()); weekStart.setHours(0,0,0,0);
                        var weekEnd = new Date(weekStart); weekEnd.setDate(weekStart.getDate() + 6); weekEnd.setHours(23,59,59,999);
                        return expenseDate >= weekStart && expenseDate <= weekEnd;
                    case 'monthly': return expenseDate.getMonth() === now.getMonth() && expenseDate.getFullYear() === now.getFullYear();
                    case 'yearly': return expenseDate.getFullYear() === now.getFullYear();
                    default: return true;
                }
            });
        }

        function updateFinanceTotals() {
            var totalBudget = financeProjects.reduce(function(sum, p) { return sum + (parseFloat(p.budget) || 0); }, 0);
            var totalExpenses = financeFilteredData.reduce(function(sum, e) { return sum + (parseFloat(e.amount) || 0); }, 0);
            var netVariance = totalBudget - totalExpenses;

            document.getElementById('totalBudgetValue').textContent = formatCurrency(totalBudget);
            document.getElementById('totalExpensesValue').textContent = formatCurrency(totalExpenses);
            var varianceEl = document.getElementById('netVarianceValue');
            varianceEl.textContent = formatCurrency(netVariance);
            varianceEl.className = 'stat-value ' + (netVariance < 0 ? 'red' : 'green');
        }

        function updateRowsInfo(totalCount) {
            var rowsInfo = document.getElementById('rowsInfoText');
            if (!rowsInfo) return;
            var currentPage = financeCurrentPage || 1;
            var pageSize = financePageSize || 25;
            var start = (currentPage - 1) * pageSize + 1;
            var end = Math.min(start + pageSize - 1, totalCount);
            rowsInfo.textContent = totalCount === 0 ? 'Showing 0 of 0 expenses' : 'Showing ' + start + '-' + end + ' of ' + totalCount + ' expenses';
        }

        // ─── API FETCH FUNCTIONS ──────────────────────────────────────
        function fetchProjects() {
            return fetch('/api/projects', {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Failed to load projects.');
                return response.json();
            })
            .then(function(data) {
                financeProjects = data || [];
                populateProjectDropdowns();
                populateProjectFilter();
                populateBudgetProjectFilter();
                populateBondProjectFilter();  // Add this line
                if (currentReportTab === 'expenses') updateFinanceTotals();
            })
            .catch(function(error) {
                console.error('fetchProjects error:', error);
                showError(error.message);
            });
        }

        function fetchExpenseCategories() {
            return apiFetch('/expense-categories')
                .then(function(data) {
                    financeCategories = data || [];
                    populateCategoryDropdown();
                })
                .catch(function(error) { showError(error.message); });
        }

        function fetchExpenses() {
            return apiFetch('/expenses')
                .then(function(data) {
                    financeExpenses = data || [];
                    financeFilteredData = financeExpenses;
                    if (currentReportTab === 'expenses') {
                        renderFinancePage(1);
                        updateFinanceTotals();
                    }
                })
                .catch(function(error) { showError(error.message); });
        }

        function fetchAssets() {
            // Fetch from company_asset_tbl via the new API endpoint
            return fetch('/api/company-assets', {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Failed to fetch assets');
                }
                return response.json();
            })
            .then(function(data) {
                assets = data.map(function(item) {
                    return {
                        asset_id: item.asset_id,
                        asset_name: item.asset_name,
                        asset_type: item.asset_type
                    };
                });
                populateAssetDropdowns();
                return assets;
            })
            .catch(function(error) {
                console.warn('Error fetching assets:', error);
                // Fallback assets
                assets = [
                    { asset_id: 1, asset_name: 'Backhoe - Komatsu PC 56', asset_type: 'heavy_equipment' },
                    { asset_id: 2, asset_name: 'Backhoe - Sumitomo', asset_type: 'heavy_equipment' },
                    { asset_id: 3, asset_name: 'Pick Up 1', asset_type: 'vehicle' },
                    { asset_id: 4, asset_name: 'Pick Up 2', asset_type: 'vehicle' },
                    { asset_id: 5, asset_name: 'Single Tire 1', asset_type: 'vehicle' },
                    { asset_id: 6, asset_name: 'Service Vehicle 1', asset_type: 'vehicle' },
                    { asset_id: 7, asset_name: 'Service Vehicle 2', asset_type: 'vehicle' },
                    { asset_id: 8, asset_name: 'Service Vehicle 3', asset_type: 'vehicle' },
                    { asset_id: 9, asset_name: 'Double Tire A', asset_type: 'vehicle' },
                    { asset_id: 10, asset_name: 'Single Tire 2', asset_type: 'vehicle' },
                    { asset_id: 11, asset_name: 'Equipment', asset_type: 'tool' },
                    { asset_id: 12, asset_name: 'Tools', asset_type: 'tool' }
                ];
                populateAssetDropdowns();
                return assets;
            });
        }

        function fetchBudgetData() {
            // Fetch from budgets_tbl directly since that's where budgets are stored
            return fetch('/api/budgets', {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Failed to load budgets.');
                return response.json();
            })
            .then(function(budgets) {
                // Get projects to map names
                return fetch('/api/projects', {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                })
                .then(function(response) { return response.json(); })
                .then(function(projects) {
                    var projectMap = {};
                    projects.forEach(function(p) { projectMap[p.project_id] = p; });
                    
                    // Calculate actual spend from expenses
                    var spendMap = {};
                    financeExpenses.forEach(function(e) {
                        if (e.project_id) {
                            spendMap[e.project_id] = (spendMap[e.project_id] || 0) + (parseFloat(e.amount) || 0);
                        }
                    });
                    
                    budgetData = budgets.map(function(b) {
                        var project = projectMap[b.project_id] || {};
                        var budgetAmount = parseFloat(b.budget_amount) || 0;
                        var actualSpend = spendMap[b.project_id] || 0;
                        var remaining = budgetAmount - actualSpend;
                        return {
                            project_id: b.project_id,
                            project_name: project.project_name || 'Unknown Project',
                            budget_amount: budgetAmount,
                            actual_amount: actualSpend,
                            remaining: remaining,
                            budget_id: b.budget_id || b.expense_id,
                            proof_file_path: b.proof_file_path || '',
                            proof_file_name: b.proof_file_name || '',
                            status: budgetAmount === 0 ? 'No Budget' : (actualSpend > budgetAmount ? 'Over Budget' : (actualSpend > budgetAmount * 0.9 ? 'Near Limit' : 'On Track'))
                        };
                    });
                    
                    // Also include projects that have expenses but no budget
                    var existingProjectIds = budgetData.map(function(b) { return b.project_id; });
                    financeProjects.forEach(function(project) {
                        if (existingProjectIds.indexOf(project.project_id) === -1) {
                            var actualSpend = spendMap[project.project_id] || 0;
                            if (actualSpend > 0) {
                                budgetData.push({
                                    project_id: project.project_id,
                                    project_name: project.project_name || 'Unknown Project',
                                    budget_amount: 0,
                                    actual_amount: actualSpend,
                                    remaining: -actualSpend,
                                    budget_id: null,
                                    proof_file_path: '',
                                    proof_file_name: '',
                                    status: 'No Budget'
                                });
                            }
                        }
                    });
                    
                    budgetFilteredData = budgetData;
                    renderBudgetPage(1);
                    updateBudgetStats();
                });
            })
            .catch(function(error) {
                showError('Error loading budget data: ' + error.message);
                document.getElementById('budgetTableBody').innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:#d32f2f;">Error loading budget data</td></tr>';
            });
        }

        // ─── POPULATE DROPDOWNS ───────────────────────────────────────
        function populateProjectDropdowns() {
            var selects = ['expenseProject', 'budgetProject', 'detailProjectEdit', 'bondProject', 'adminExpProject', 'receivableProject', 'backhoeExpenseProject', 'backhoeRentalProject', 'contractProject'];
            selects.forEach(function(id) {
                var select = document.getElementById(id);
                if (!select) return;
                select.innerHTML = '<option value="">Select Project...</option>';
                financeProjects.forEach(function(project) {
                    var option = document.createElement('option');
                    option.value = project.project_id;
                    option.textContent = project.project_name;
                    select.appendChild(option);
                });
            });
        }

        function populateCategoryDropdown() {
            var selects = ['expenseCategory', 'detailCategoryEdit'];
            selects.forEach(function(id) {
                var select = document.getElementById(id);
                if (!select) return;
                select.innerHTML = '<option value="">Select Category...</option>';
                financeCategories.forEach(function(category) {
                    var option = document.createElement('option');
                    option.value = category.fin_category_id || category.expense_category_id;
                    option.textContent = category.category_name || category.category_code || category;
                    select.appendChild(option);
                });
            });
        }

        function populateProjectFilter() {
            var filter = document.getElementById('projectFilter');
            filter.innerHTML = '<option value="all">All Projects</option>';
            financeProjects.forEach(function(project) {
                var option = document.createElement('option');
                option.value = project.project_name;
                option.textContent = project.project_name;
                filter.appendChild(option);
            });
        }

        function populateBudgetProjectFilter() {
            var filter = document.getElementById('budgetProjectFilter');
            filter.innerHTML = '<option value="all">All Projects</option>';
            financeProjects.forEach(function(project) {
                var option = document.createElement('option');
                option.value = project.project_name;
                option.textContent = project.project_name;
                filter.appendChild(option);
            });
        }

        function populateAssetDropdowns() {
            var selects = ['repairAssetSelect', 'backhoeExpenseAsset', 'backhoeRentalAsset', 'repairAsset'];
            
            selects.forEach(function(id) {
                var select = document.getElementById(id);
                if (!select) return;
                
                var currentValue = select.value;
                select.innerHTML = '<option value="">Select Asset...</option>';
                
                assets.forEach(function(asset) {
                    var option = document.createElement('option');
                    option.value = asset.asset_id;
                    option.textContent = asset.asset_name;
                    select.appendChild(option);
                });
                
                if (currentValue) {
                    var options = select.querySelectorAll('option');
                    for (var i = 0; i < options.length; i++) {
                        if (options[i].value === currentValue) {
                            select.value = currentValue;
                            break;
                        }
                    }
                }
            });

            // Cash account dropdown
            var cashSelect = document.getElementById('cashAccount');
            if (cashSelect) {
                var defaultAccounts = [
                    { id: 1, name: 'Cash on Hand' },
                    { id: 2, name: 'Cash on Hand - Field' },
                    { id: 3, name: 'Treasury - EVCA' },
                    { id: 4, name: 'Treasury - OB' },
                    { id: 5, name: 'Treasury - OP' },
                    { id: 6, name: 'Treasury' },
                    { id: 7, name: 'Treasury - EVCA Corp' },
                    { id: 8, name: 'Treasury (PhilHealth Purposes)' }
                ];
                cashSelect.innerHTML = '<option value="">Select Account...</option>';
                defaultAccounts.forEach(function(acc) {
                    var option = document.createElement('option');
                    option.value = acc.id;
                    option.textContent = acc.name;
                    cashSelect.appendChild(option);
                });
            }
        }

        function populateBondProjectFilter() {
            var filter = document.getElementById('bondProjectFilter');
            if (!filter) return;
            
            filter.innerHTML = '<option value="all">All Projects</option>';
            financeProjects.forEach(function(project) {
                var option = document.createElement('option');
                option.value = project.project_id;
                option.textContent = project.project_name;
                filter.appendChild(option);
            });
        }

        // ─── RENDER EXPENSES ───────────────────────────────────────────
        function renderFinancePage(page) {
            financeCurrentPage = page;
            var start = (page - 1) * financePageSize;
            var end = Math.min(start + financePageSize, financeFilteredData.length);
            var pageData = financeFilteredData.slice(start, end);

            var tbody = document.getElementById('expenseTableBody');
            tbody.innerHTML = '';

            if (!pageData.length) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;">No expenses found.</td></tr>';
                renderFinancePagination();
                updateRowsInfo(0);
                return;
            }

            pageData.forEach(function(expense) {
                var row = document.createElement('tr');
                row.setAttribute('data-expense-id', expense.fin_expense_id || expense.expense_id);
                row.setAttribute('data-project-id', expense.project_id || '');
                row.setAttribute('data-project', expense.project_name || '');
                row.setAttribute('data-desc', expense.expense_description || '');
                row.setAttribute('data-category-id', expense.fin_category_id || expense.expense_category_id || '');
                row.setAttribute('data-category', expense.category_name || '');
                row.setAttribute('data-amount', expense.amount || '0');
                row.setAttribute('data-date', expense.expense_date || '');
                row.setAttribute('data-remarks', expense.remarks || '');
                row.setAttribute('data-proof-file-path', expense.proof_file_path || '');
                row.setAttribute('data-proof-file-name', expense.proof_file_name || '');
                row.onclick = function() { openExpenseModal(this); };

                var categoryName = expense.category_name || '';
                var categoryClass = categoryName.toLowerCase().replace(/[^a-z]/g, '-');
                if (['labor', 'material', 'equipment', 'other'].indexOf(categoryClass) === -1) categoryClass = 'other';

                row.innerHTML = '<td><strong>' + (expense.project_name || '') + '</strong></td>' +
                    '<td>' + (expense.expense_description || '') + '</td>' +
                    '<td><span class="category-badge ' + categoryClass + '">' + categoryName + '</span></td>' +
                    '<td>' + formatCurrency(expense.amount || 0) + '</td>' +
                    '<td>' + (expense.expense_date || '') + '</td>' +
                    '<td>' + (expense.remarks || '—') + '</td>';
                tbody.appendChild(row);
            });

            renderFinancePagination();
            updateRowsInfo(financeFilteredData.length);
        }

        function renderFinancePagination() {
            var container = document.getElementById('financePaginationLinks');
            if (!container) return;
            var total = financeFilteredData.length;
            var totalPages = Math.ceil(total / financePageSize);
            var current = financeCurrentPage;
            if (totalPages <= 1) { container.innerHTML = ''; return; }

            var html = '';
            html += '<a href="#" onclick="renderFinancePage(' + (current - 1) + '); return false;" class="' + (current <= 1 ? 'disabled' : '') + '">«</a>';

            if (totalPages <= 7) {
                for (var i = 1; i <= totalPages; i++) {
                    html += '<a href="#" onclick="renderFinancePage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
            } else {
                for (var i = 1; i <= 3; i++) html += '<a href="#" onclick="renderFinancePage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                if (current > 4) html += '<span class="dots">...</span>';
                var startPage = Math.max(4, current - 1);
                var endPage = Math.min(totalPages - 2, current + 1);
                for (var i = startPage; i <= endPage; i++) html += '<a href="#" onclick="renderFinancePage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                if (current < totalPages - 3) html += '<span class="dots">...</span>';
                for (var i = totalPages - 1; i <= totalPages; i++) {
                    if (i > 3) html += '<a href="#" onclick="renderFinancePage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
            }
            html += '<a href="#" onclick="renderFinancePage(' + (current + 1) + '); return false;" class="' + (current >= totalPages ? 'disabled' : '') + '">»</a>';
            container.innerHTML = html;
        }

        function changeFinancePageSize() {
            financePageSize = parseInt(document.getElementById('financeRowsPerPage').value) || 25;
            financeCurrentPage = 1;
            renderFinancePage(1);
        }

        // ─── RENDER BUDGETS ────────────────────────────────────────────
        function renderBudgetPage(page) {
            budgetCurrentPage = page;
            var start = (page - 1) * budgetPageSize;
            var end = Math.min(start + budgetPageSize, budgetFilteredData.length);
            var pageData = budgetFilteredData.slice(start, end);

            var tbody = document.getElementById('budgetTableBody');
            tbody.innerHTML = '';

            if (!pageData.length) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;">No budget data found.</td></tr>';
                renderBudgetPagination();
                updateBudgetRowsInfo(0);
                return;
            }

            pageData.forEach(function(budget) {
                var row = document.createElement('tr');
                var budgetAmount = parseFloat(budget.budget_amount) || 0;
                var actualAmount = parseFloat(budget.actual_amount) || 0;
                var remaining = parseFloat(budget.remaining) || 0;
                var status = budget.status || 'No Budget';

                var rowClass = '';
                if (remaining < 0) rowClass = 'budget-overrun';
                else if (remaining < budgetAmount * 0.1 && budgetAmount > 0) rowClass = 'budget-warning';

                row.className = rowClass;
                row.setAttribute('data-budget-id', budget.budget_id || '');
                row.setAttribute('data-project-id', budget.project_id);
                row.setAttribute('data-project-name', budget.project_name || 'Unnamed Project');
                row.setAttribute('data-budget-amount', budgetAmount);
                row.setAttribute('data-actual-amount', actualAmount);
                row.setAttribute('data-remaining', remaining);
                row.setAttribute('data-proof-file-path', budget.proof_file_path || '');
                row.setAttribute('data-proof-file-name', budget.proof_file_name || '');
                row.onclick = function() { openBudgetModal(this); };

                var statusBadge = '';
                if (status === 'On Track') statusBadge = '<span class="badge badge-success">On Track</span>';
                else if (status === 'Near Limit') statusBadge = '<span class="badge badge-warning">Near Limit</span>';
                else if (status === 'Over Budget') statusBadge = '<span class="badge badge-danger">Over Budget</span>';
                else statusBadge = '<span class="badge badge-secondary">No Budget</span>';

                row.innerHTML = '<td><strong>' + (budget.project_name || 'Unnamed Project') + '</strong></td>' +
                    '<td>' + formatCurrency(budgetAmount) + '</td>' +
                    '<td>' + formatCurrency(actualAmount) + '</td>' +
                    '<td style="' + (remaining < 0 ? 'color:#d32f2f;' : (remaining < budgetAmount * 0.1 && budgetAmount > 0 ? 'color:#f57c00;' : 'color:#2e7d32;')) + '">' + formatCurrency(remaining) + '</td>' +
                    '<td>' + statusBadge + '</td>';
                tbody.appendChild(row);
            });

            renderBudgetPagination();
            updateBudgetRowsInfo(budgetFilteredData.length);
        }

        function renderBudgetPagination() {
            var container = document.getElementById('budgetPaginationLinks');
            if (!container) return;
            var total = budgetFilteredData.length;
            var totalPages = Math.ceil(total / budgetPageSize);
            var current = budgetCurrentPage;
            if (totalPages <= 1) { container.innerHTML = ''; return; }

            var html = '';
            html += '<a href="#" onclick="renderBudgetPage(' + (current - 1) + '); return false;" class="' + (current <= 1 ? 'disabled' : '') + '">«</a>';

            if (totalPages <= 7) {
                for (var i = 1; i <= totalPages; i++) {
                    html += '<a href="#" onclick="renderBudgetPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
            } else {
                for (var i = 1; i <= 3; i++) html += '<a href="#" onclick="renderBudgetPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                if (current > 4) html += '<span class="dots">...</span>';
                var startPage = Math.max(4, current - 1);
                var endPage = Math.min(totalPages - 2, current + 1);
                for (var i = startPage; i <= endPage; i++) html += '<a href="#" onclick="renderBudgetPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                if (current < totalPages - 3) html += '<span class="dots">...</span>';
                for (var i = totalPages - 1; i <= totalPages; i++) {
                    if (i > 3) html += '<a href="#" onclick="renderBudgetPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
            }
            html += '<a href="#" onclick="renderBudgetPage(' + (current + 1) + '); return false;" class="' + (current >= totalPages ? 'disabled' : '') + '">»</a>';
            container.innerHTML = html;
        }

        function changeBudgetPageSize() {
            budgetPageSize = parseInt(document.getElementById('budgetRowsPerPage').value) || 25;
            budgetCurrentPage = 1;
            renderBudgetPage(1);
        }

        function updateBudgetRowsInfo(totalCount) {
            var rowsInfo = document.getElementById('budgetRowsInfo');
            if (!rowsInfo) return;
            var currentPage = budgetCurrentPage || 1;
            var pageSize = budgetPageSize || 25;
            var start = (currentPage - 1) * pageSize + 1;
            var end = Math.min(start + pageSize - 1, totalCount);
            rowsInfo.textContent = totalCount === 0 ? 'Showing 0 of 0 projects' : 'Showing ' + start + '-' + end + ' of ' + totalCount + ' projects';
        }

        function updateBudgetStats() {
            var totalBudget = budgetFilteredData.reduce(function(sum, item) {
                return sum + (parseFloat(item.budget_amount) || 0);
            }, 0);
            document.getElementById('budgetTotalValue').textContent = formatCurrency(totalBudget);
        }

        function filterBudgetTable() {
            var searchTerm = document.getElementById('budgetSearch').value.toLowerCase().trim();
            var projectFilter = document.getElementById('budgetProjectFilter').value;

            budgetSearchTerm = searchTerm;
            budgetProjectFilter = projectFilter;

            var projectFiltered = projectFilter === 'all'
                ? budgetData
                : budgetData.filter(function(item) { return item.project_name === projectFilter; });

            budgetFilteredData = searchTerm
                ? projectFiltered.filter(function(item) { return (item.project_name || '').toLowerCase().includes(searchTerm); })
                : projectFiltered;

            renderBudgetPage(1);
            updateBudgetStats();
        }

        function clearBudgetSearch() {
            document.getElementById('budgetSearch').value = '';
            document.getElementById('budgetProjectFilter').value = 'all';
            budgetSearchTerm = '';
            budgetProjectFilter = 'all';
            filterBudgetTable();
        }

        // ─── BUDGET DETAIL MODAL ──────────────────────────────────────
        function openBudgetModal(row) {
            currentBudgetRow = row;
            document.getElementById('budgetDetailProjectDisplay').textContent = row.dataset.projectName;
            document.getElementById('budgetDetailAmountDisplay').textContent = formatCurrency(row.dataset.budgetAmount);
            document.getElementById('budgetDetailActualDisplay').textContent = formatCurrency(row.dataset.actualAmount);
            document.getElementById('budgetDetailRemainingDisplay').textContent = formatCurrency(row.dataset.remaining);
            document.getElementById('budgetDetailProjectDisplay').setAttribute('data-project-id', row.dataset.projectId);
            document.getElementById('budgetDetailAmountEdit').value = row.dataset.budgetAmount;

            selectedBudgetDetailFile = null;
            budgetFileMarkedForRemoval = false;
            var budgetDetailFileInput = document.getElementById('detailBudgetFile');
            if (budgetDetailFileInput) budgetDetailFileInput.value = '';
            var budFileNameDisplay = document.getElementById('budgetFileNameDisplay');
            var budFileIcon = document.getElementById('budgetFileIcon');
            var budViewButton = document.getElementById('viewBudgetFileBtn');
            var budDeleteButton = document.getElementById('deleteBudgetFileBtn');
            var budPreviewContainer = document.getElementById('budgetFilePreviewContainer');
            if (budPreviewContainer) budPreviewContainer.style.display = 'none';
            if (budDeleteButton) budDeleteButton.style.display = 'none';

            var existingBudgetProofPath = row.getAttribute('data-proof-file-path');
            var existingBudgetProofName = row.getAttribute('data-proof-file-name');
            if (existingBudgetProofPath) {
                if (budFileNameDisplay) budFileNameDisplay.textContent = existingBudgetProofName || existingBudgetProofPath;
                if (budFileIcon) budFileIcon.textContent = existingBudgetProofPath.toLowerCase().endsWith('.pdf') ? '📄' : '🖼️';
                if (budViewButton) {
                    budViewButton.style.display = 'inline-block';
                    budViewButton.setAttribute('data-file-url', '/storage/' + existingBudgetProofPath);
                }
            } else {
                if (budFileNameDisplay) budFileNameDisplay.textContent = 'No file attached';
                if (budFileIcon) budFileIcon.textContent = '📎';
                if (budViewButton) budViewButton.style.display = 'none';
            }

            if (isBudgetEditMode) toggleBudgetDetailEdit();
            isBudgetEditMode = false;
            document.getElementById('budgetDetailEditBtn').style.display = 'inline-block';
            document.getElementById('budgetDetailDeleteBtn').style.display = 'inline-block';
            document.getElementById('budgetDetailSaveBtn').style.display = 'none';
            document.querySelectorAll('.budget-detail-edit').forEach(function(el) { el.style.display = 'none'; });
            document.querySelectorAll('.budget-detail-value').forEach(function(el) { el.style.display = ''; });

            document.getElementById('budgetDetailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeBudgetDetailModal() {
            document.getElementById('budgetDetailModal').classList.remove('active');
            document.body.style.overflow = '';
            if (isBudgetEditMode) toggleBudgetDetailEdit();
        }

        function toggleBudgetDetailEdit() {
            isBudgetEditMode = !isBudgetEditMode;
            var displayEls = document.querySelectorAll('.budget-detail-value');
            var editEls = document.querySelectorAll('.budget-detail-edit');
            var editBtn = document.getElementById('budgetDetailEditBtn');
            var deleteBtn = document.getElementById('budgetDetailDeleteBtn');
            var saveBtn = document.getElementById('budgetDetailSaveBtn');
            var changeFileBtn = document.getElementById('changeBudgetFileBtn');
            var deleteFileBtn = document.getElementById('deleteBudgetFileBtn');
            var fileNameDisplay = document.getElementById('budgetFileNameDisplay');

            if (isBudgetEditMode) {
                editBtn.style.display = 'none';
                deleteBtn.style.display = 'none';
                saveBtn.style.display = 'inline-block';
                if (changeFileBtn) changeFileBtn.style.display = 'inline-block';
                displayEls.forEach(function(el) { el.style.display = 'none'; });
                editEls.forEach(function(el) { el.style.display = ''; });
                var hasFile = selectedBudgetDetailFile || (fileNameDisplay && fileNameDisplay.textContent !== 'No file attached');
                if (deleteFileBtn) deleteFileBtn.style.display = hasFile ? 'inline-block' : 'none';
            } else {
                editBtn.style.display = 'inline-block';
                deleteBtn.style.display = 'inline-block';
                saveBtn.style.display = 'none';
                if (changeFileBtn) changeFileBtn.style.display = 'none';
                if (deleteFileBtn) deleteFileBtn.style.display = 'none';
                displayEls.forEach(function(el) { el.style.display = ''; });
                editEls.forEach(function(el) { el.style.display = 'none'; });
            }
        }

        function saveBudgetDetailChanges() {
            if (!currentBudgetRow) return;
            var projectId = document.getElementById('budgetDetailProjectDisplay').getAttribute('data-project-id');
            var budgetAmount = parseFloat(document.getElementById('budgetDetailAmountEdit').value) || 0;

            if (!projectId) { showError('Project information is missing.'); return; }
            if (budgetAmount <= 0) { showError('Budget amount must be greater than 0.'); return; }

            var budgetDetailFormData = new FormData();
            budgetDetailFormData.append('project_id', projectId);
            budgetDetailFormData.append('budget_amount', budgetAmount);
            if (selectedBudgetDetailFile) {
                budgetDetailFormData.append('proof_file', selectedBudgetDetailFile);
            } else if (budgetFileMarkedForRemoval) {
                budgetDetailFormData.append('remove_proof_file', '1');
            }

            fetch('/api/budgets', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: budgetDetailFormData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) { throw new Error(data.message || 'Unable to update budget.'); });
                }
                return response.json();
            })
            .then(function() {
                closeBudgetDetailModal();
                showSuccess('Budget updated successfully!');
                fetchBudgetData();
            })
            .catch(function(error) { showError(error.message); });
        }

        function deleteBudget() {
            if (!currentBudgetRow) return;
            var projectId = currentBudgetRow.getAttribute('data-project-id');
            
            openBudgetDeleteModal('Are you sure you want to delete the budget for this project?', function() {
                // Find the budget record
                var budget = budgetData.find(function(b) { 
                    return String(b.project_id) === String(projectId); 
                });
                
                if (budget && budget.budget_id) {
                    apiFetch('/project-contracts/' + budget.budget_id, { method: 'DELETE' })
                        .then(function() {
                            budgetData = budgetData.filter(function(item) {
                                return String(item.project_id) !== String(projectId);
                            });
                            currentBudgetRow.remove();
                            closeBudgetDeleteModal();
                            updateBudgetStats();
                            showSuccess('Budget deleted successfully!');
                            currentBudgetRow = null;
                            fetchBudgetData();
                        })
                        .catch(function(error) { showError(error.message); });
                } else {
                    // Just remove from display
                    budgetData = budgetData.filter(function(item) {
                        return String(item.project_id) !== String(projectId);
                    });
                    currentBudgetRow.remove();
                    closeBudgetDeleteModal();
                    updateBudgetStats();
                    showSuccess('Budget removed from view.');
                    currentBudgetRow = null;
                    fetchBudgetData();
                }
            });
        }

        document.getElementById('budgetDetailModal').addEventListener('click', function(e) {
            if (e.target === this) closeBudgetDetailModal();
        });

        // ─── EXPENSE DETAIL MODAL ─────────────────────────────────────
        function openExpenseModal(row) {
            currentDetailRow = row;
            document.getElementById('detailProjectDisplay').textContent = row.dataset.project;
            document.getElementById('detailDescDisplay').textContent = row.dataset.desc;
            document.getElementById('detailCategoryDisplay').textContent = row.dataset.category;
            document.getElementById('detailAmountDisplay').textContent = formatCurrency(row.dataset.amount);
            document.getElementById('detailDateDisplay').textContent = row.dataset.date;
            document.getElementById('detailRemarksDisplay').textContent = row.dataset.remarks || '—';

            document.getElementById('detailProjectEdit').value = row.dataset.projectId || '';
            document.getElementById('detailDescEdit').value = row.dataset.desc;
            document.getElementById('detailCategoryEdit').value = row.dataset.categoryId || '';
            document.getElementById('detailAmountEdit').value = row.dataset.amount;
            document.getElementById('detailDateEdit').value = row.dataset.date;
            document.getElementById('detailRemarksEdit').value = row.dataset.remarks || '';

            selectedDetailFile = null;
            expenseFileMarkedForRemoval = false;
            var detailFileInput = document.getElementById('detailExpenseFile');
            if (detailFileInput) detailFileInput.value = '';
            var expFileNameDisplay = document.getElementById('expenseFileNameDisplay');
            var expFileIcon = document.getElementById('expenseFileIcon');
            var expViewButton = document.getElementById('viewExpenseFileBtn');
            var expDeleteButton = document.getElementById('deleteExpenseFileBtn');
            var expPreviewContainer = document.getElementById('filePreviewContainer');
            if (expPreviewContainer) expPreviewContainer.style.display = 'none';
            if (expDeleteButton) expDeleteButton.style.display = 'none';

            var existingExpenseProofPath = row.getAttribute('data-proof-file-path');
            var existingExpenseProofName = row.getAttribute('data-proof-file-name');
            if (existingExpenseProofPath) {
                if (expFileNameDisplay) expFileNameDisplay.textContent = existingExpenseProofName || existingExpenseProofPath;
                if (expFileIcon) expFileIcon.textContent = existingExpenseProofPath.toLowerCase().endsWith('.pdf') ? '📄' : '🖼️';
                if (expViewButton) {
                    expViewButton.style.display = 'inline-block';
                    expViewButton.setAttribute('data-file-url', '/storage/' + existingExpenseProofPath);
                }
            } else {
                if (expFileNameDisplay) expFileNameDisplay.textContent = 'No file attached';
                if (expFileIcon) expFileIcon.textContent = '📎';
                if (expViewButton) expViewButton.style.display = 'none';
            }

            if (isEditMode) toggleDetailEdit();
            isEditMode = false;
            document.getElementById('detailEditBtn').style.display = 'inline-block';
            document.getElementById('detailDeleteBtn').style.display = 'inline-block';
            document.getElementById('detailSaveBtn').style.display = 'none';
            document.querySelectorAll('.detail-edit').forEach(function(el) { el.style.display = 'none'; });
            document.querySelectorAll('.detail-value').forEach(function(el) { el.style.display = ''; });

            document.getElementById('expenseDetailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeExpenseDetailModal() {
            document.getElementById('expenseDetailModal').classList.remove('active');
            document.body.style.overflow = '';
            if (isEditMode) toggleDetailEdit();
        }

        function toggleDetailEdit() {
            isEditMode = !isEditMode;
            var displayEls = document.querySelectorAll('.detail-value');
            var editEls = document.querySelectorAll('.detail-edit');
            var editBtn = document.getElementById('detailEditBtn');
            var deleteBtn = document.getElementById('detailDeleteBtn');
            var saveBtn = document.getElementById('detailSaveBtn');
            var changeFileBtn = document.getElementById('changeExpenseFileBtn');
            var deleteFileBtn = document.getElementById('deleteExpenseFileBtn');
            var fileNameDisplay = document.getElementById('expenseFileNameDisplay');

            if (isEditMode) {
                editBtn.style.display = 'none';
                deleteBtn.style.display = 'none';
                saveBtn.style.display = 'inline-block';
                if (changeFileBtn) changeFileBtn.style.display = 'inline-block';
                displayEls.forEach(function(el) { el.style.display = 'none'; });
                editEls.forEach(function(el) { el.style.display = ''; });
                var hasFile = selectedDetailFile || (fileNameDisplay && fileNameDisplay.textContent !== 'No file attached');
                if (deleteFileBtn) deleteFileBtn.style.display = hasFile ? 'inline-block' : 'none';
            } else {
                editBtn.style.display = 'inline-block';
                deleteBtn.style.display = 'inline-block';
                saveBtn.style.display = 'none';
                if (changeFileBtn) changeFileBtn.style.display = 'none';
                if (deleteFileBtn) deleteFileBtn.style.display = 'none';
                displayEls.forEach(function(el) { el.style.display = ''; });
                editEls.forEach(function(el) { el.style.display = 'none'; });
            }
        }

        function saveDetailChanges() {
            if (!currentDetailRow) return;
            var projectId = document.getElementById('detailProjectEdit').value;
            var desc = document.getElementById('detailDescEdit').value.trim();
            var categoryId = document.getElementById('detailCategoryEdit').value;
            var amount = parseFloat(document.getElementById('detailAmountEdit').value) || 0;
            var date = document.getElementById('detailDateEdit').value;
            var remarks = document.getElementById('detailRemarksEdit').value.trim();

            if (!projectId || !desc || !categoryId || !amount || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            if (amount <= 0) { showError('Amount must be greater than 0.'); return; }

            var expenseId = currentDetailRow.getAttribute('data-expense-id');

            var detailFormData = new FormData();
            detailFormData.append('_method', 'PUT');
            detailFormData.append('project_id', projectId);
            detailFormData.append('fin_category_id', categoryId);
            detailFormData.append('expense_description', desc);
            detailFormData.append('amount', amount);
            detailFormData.append('expense_date', date);
            if (remarks) detailFormData.append('remarks', remarks);
            if (selectedDetailFile) {
                detailFormData.append('proof_file', selectedDetailFile);
            } else if (expenseFileMarkedForRemoval) {
                detailFormData.append('remove_proof_file', '1');
            }

            fetch(API_BASE + '/expenses/' + expenseId, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: detailFormData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) { throw new Error(data.message || 'Unable to update expense.'); });
                }
                return response.json();
            })
            .then(function() {
                closeExpenseDetailModal();
                showSuccess('Expense updated successfully!');
                fetchExpenses();
            })
            .catch(function(error) { showError('Error updating expense: ' + error.message); });
        }

        function deleteExpense() {
            if (!currentDetailRow) return;
            openDeleteModal('Are you sure you want to permanently delete this expense?', function() {
                var expenseId = currentDetailRow.getAttribute('data-expense-id');
                apiFetch('/expenses/' + expenseId, { method: 'DELETE' })
                    .then(function() {
                        financeExpenses = financeExpenses.filter(function(item) {
                            return String(item.fin_expense_id || item.expense_id) !== String(expenseId);
                        });
                        closeExpenseDetailModal();
                        applyFilters();
                        showSuccess('Expense deleted successfully!');
                        currentDetailRow = null;
                    })
                    .catch(function(error) { showError(error.message); });
            });
        }

        // ─── ADD EXPENSE ──────────────────────────────────────────────
        function openAddExpenseModal() {
            document.getElementById('addExpenseModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('expenseProject').value = '';
            document.getElementById('expenseDesc').value = '';
            document.getElementById('expenseCategory').value = '';
            document.getElementById('expenseAmount').value = '';
            document.getElementById('expenseDate').value = '{{ date("Y-m-d") }}';
            document.getElementById('expenseRemarks').value = '';

            var expenseProofFileInput = document.getElementById('expenseProofFile');
            var expenseProofFileNameEl = document.getElementById('expenseProofFileName');
            if (expenseProofFileInput) expenseProofFileInput.value = '';
            if (expenseProofFileNameEl) expenseProofFileNameEl.textContent = 'No file chosen';
        }

        function closeAddExpenseModal() {
            document.getElementById('addExpenseModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function saveExpense() {
            var projectId = document.getElementById('expenseProject').value;
            var desc = document.getElementById('expenseDesc').value.trim();
            var categoryId = document.getElementById('expenseCategory').value;
            var amount = parseFloat(document.getElementById('expenseAmount').value) || 0;
            var date = document.getElementById('expenseDate').value;
            var remarks = document.getElementById('expenseRemarks').value.trim();

            if (!projectId || !desc || !categoryId || !amount || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            if (amount <= 0) { showError('Amount must be greater than 0.'); return; }

            var expenseFormData = new FormData();
            expenseFormData.append('project_id', projectId);
            expenseFormData.append('fin_category_id', categoryId);
            expenseFormData.append('expense_description', desc);
            expenseFormData.append('amount', amount);
            expenseFormData.append('expense_date', date);
            if (remarks) expenseFormData.append('remarks', remarks);
            var expenseProofFileInput = document.getElementById('expenseProofFile');
            if (expenseProofFileInput && expenseProofFileInput.files && expenseProofFileInput.files.length > 0) {
                expenseFormData.append('proof_file', expenseProofFileInput.files[0]);
            }

            fetch(API_BASE + '/expenses', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: expenseFormData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) { throw new Error(data.message || 'Unable to save expense.'); });
                }
                return response.json();
            })
            .then(function() {
                closeAddExpenseModal();
                showSuccess('Expense added successfully!');
                fetchExpenses();
            })
            .catch(function(error) { showError(error.message); });
        }

        // ─── ADD BUDGET ───────────────────────────────────────────────
        function openAddBudgetModal() {
            document.getElementById('addBudgetModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('budgetProject').value = '';
            document.getElementById('budgetAmount').value = '';

            var budgetProofFileInput = document.getElementById('budgetProofFile');
            var budgetProofFileNameEl = document.getElementById('budgetProofFileName');
            if (budgetProofFileInput) budgetProofFileInput.value = '';
            if (budgetProofFileNameEl) budgetProofFileNameEl.textContent = 'No file chosen';
        }

        function closeAddBudgetModal() {
            document.getElementById('addBudgetModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function saveBudget() {
            var projectId = document.getElementById('budgetProject').value;
            var amount = document.getElementById('budgetAmount').value;

            if (!projectId || !amount || parseFloat(amount) <= 0) {
                showError('Please select a project and enter a valid budget amount.');
                return;
            }

            var budgetFormData = new FormData();
            budgetFormData.append('project_id', projectId);
            budgetFormData.append('budget_amount', amount);
            var budgetProofFileInput = document.getElementById('budgetProofFile');
            if (budgetProofFileInput && budgetProofFileInput.files && budgetProofFileInput.files.length > 0) {
                budgetFormData.append('proof_file', budgetProofFileInput.files[0]);
            }

            fetch('/api/budgets', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: budgetFormData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) { throw new Error(data.message || 'Unable to save budget.'); });
                }
                return response.json();
            })
            .then(function() {
                closeAddBudgetModal();
                showSuccess('Budget added successfully!');
                fetchBudgetData();
            })
            .catch(function(error) { showError(error.message); });
        }

        // ─── ADD ADMIN EXPENSE ────────────────────────────────────────
        function openAddAdminExpModal() {
            document.getElementById('addAdminExpModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('adminExpProject').value = '';
            document.getElementById('adminExpCategory').value = '';
            document.getElementById('adminExpAmount').value = '';
            document.getElementById('adminExpDate').value = '{{ date("Y-m-d") }}';
            document.getElementById('adminExpRemarks').value = '';
        }

        function closeAddAdminExpModal() {
            document.getElementById('addAdminExpModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function saveAdminExp() {
            var projectId = document.getElementById('adminExpProject').value;
            var categoryCode = document.getElementById('adminExpCategory').value;
            var amount = parseFloat(document.getElementById('adminExpAmount').value) || 0;
            var date = document.getElementById('adminExpDate').value;
            var remarks = document.getElementById('adminExpRemarks').value.trim();

            if (!projectId || !categoryCode || !amount || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            if (amount <= 0) { showError('Amount must be greater than 0.'); return; }

            var category = financeCategories.find(function(c) {
                return c.category_code === categoryCode || c.category_name === categoryCode;
            });

            if (!category) {
                showError('Invalid category selected.');
                return;
            }

            var payload = {
                project_id: parseInt(projectId),
                fin_category_id: parseInt(category.fin_category_id || category.expense_category_id),
                expense_description: remarks || categoryCode,
                amount: amount,
                expense_date: date,
                remarks: remarks || null
            };

            apiFetch('/expenses', { method: 'POST', body: JSON.stringify(payload) })
                .then(function() {
                    closeAddAdminExpModal();
                    showSuccess('Admin expense added successfully!');
                    loadAdminExp();
                })
                .catch(function(error) { showError(error.message); });
        }

        // ─── ADD CONTRACT ─────────────────────────────────────────────
        function openAddContractModal(row) {
            document.getElementById('addContractModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('contractProject').value = '';
            document.getElementById('contractPrice').value = '';
            document.getElementById('contractAddlWorks').value = '0';
            document.getElementById('contractPayment').value = '0';
            document.getElementById('contractAddlPayment').value = '0';
            document.getElementById('contractRemarks').value = '';

            if (row) {
                document.querySelector('#addContractModal h2').textContent = 'Edit Contract';
                document.getElementById('contractProject').value = row.dataset.projectId;
                document.getElementById('contractPrice').value = row.dataset.contractPrice;
                document.getElementById('contractAddlWorks').value = row.dataset.addlWorks || 0;
                document.getElementById('contractPayment').value = row.dataset.payment || 0;
                document.getElementById('contractAddlPayment').value = row.dataset.addlPayment || 0;
                document.getElementById('contractRemarks').value = row.dataset.remarks || '';
                document.getElementById('addContractModal').setAttribute('data-edit-id', row.dataset.contractId || '');
            } else {
                document.querySelector('#addContractModal h2').textContent = 'Add Contract';
                document.getElementById('addContractModal').removeAttribute('data-edit-id');
            }
        }

        function closeAddContractModal() {
            document.getElementById('addContractModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function saveContract() {
            var projectId = document.getElementById('contractProject').value;
            var price = parseFloat(document.getElementById('contractPrice').value) || 0;
            var addlWorks = parseFloat(document.getElementById('contractAddlWorks').value) || 0;
            var payment = parseFloat(document.getElementById('contractPayment').value) || 0;
            var addlPayment = parseFloat(document.getElementById('contractAddlPayment').value) || 0;
            var remarks = document.getElementById('contractRemarks').value.trim();
            var editId = document.getElementById('addContractModal').getAttribute('data-edit-id');

            if (!projectId || price <= 0) {
                showError('Please select a project and enter a valid contract price.');
                return;
            }

            var payload = {
                project_id: parseInt(projectId),
                original_contract_price: price,
                additional_works_contract: addlWorks,
                original_payment_received: payment,
                additional_works_payment: addlPayment,
                remarks: remarks || null
            };

            var endpoint = editId ? '/project-contracts/' + editId : '/project-contracts';
            var method = editId ? 'PUT' : 'POST';

            apiFetch(endpoint, { method: method, body: JSON.stringify(payload) })
                .then(function() {
                    closeAddContractModal();
                    showSuccess('Contract ' + (editId ? 'updated' : 'added') + ' successfully!');
                    loadProfit();
                })
                .catch(function(error) { showError(error.message); });
        }

        // ─── ADD RECEIVABLE ──────────────────────────────────────────
        function openAddReceivableModal() {
            document.getElementById('addReceivableModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('receivableEntryType').value = 'accounts_receivable';
            document.getElementById('receivableCounterparty').value = '';
            document.getElementById('receivableProject').value = '';
            document.getElementById('receivableDate').value = '{{ date("Y-m-d") }}';
            document.getElementById('receivable30d').value = '';
            document.getElementById('receivable60d').value = '';
            document.getElementById('receivable90d').value = '';
            document.getElementById('receivable120d').value = '';
            document.getElementById('receivableStatus').value = 'outstanding';
            document.getElementById('receivableRemarks').value = '';
        }

        function closeAddReceivableModal() {
            document.getElementById('addReceivableModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function saveReceivable() {
            var entryType = document.getElementById('receivableEntryType').value;
            var counterparty = document.getElementById('receivableCounterparty').value.trim();
            var projectId = document.getElementById('receivableProject').value;
            var date = document.getElementById('receivableDate').value;
            var amt30d = parseFloat(document.getElementById('receivable30d').value) || 0;
            var amt60d = parseFloat(document.getElementById('receivable60d').value) || 0;
            var amt90d = parseFloat(document.getElementById('receivable90d').value) || 0;
            var amt120d = parseFloat(document.getElementById('receivable120d').value) || 0;
            var status = document.getElementById('receivableStatus').value;
            var remarks = document.getElementById('receivableRemarks').value.trim();

            if (!entryType || !counterparty || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            if (amt30d === 0 && amt60d === 0 && amt90d === 0 && amt120d === 0) {
                showError('Please enter at least one amount.');
                return;
            }

            var payload = {
                entry_type: entryType,
                counterparty_name: counterparty,
                project_id: projectId || null,
                entry_date: date,
                amount_30d: amt30d,
                amount_31_60d: amt60d,
                amount_61_90d: amt90d,
                amount_91_120d: amt120d,
                status: status,
                remarks: remarks || null
            };

            apiFetch('/receivables-payables', { method: 'POST', body: JSON.stringify(payload) })
                .then(function() {
                    closeAddReceivableModal();
                    showSuccess('Entry added successfully!');
                    loadReceivables();
                })
                .catch(function(error) { showError(error.message); });
        }

        // ─── ADD CASH POSITION ────────────────────────────────────────
        function openAddCashModal() {
            document.getElementById('addCashModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('cashAccount').value = '';
            document.getElementById('cashPeriod').value = '{{ date("Y-m") }}';
            document.getElementById('cashBalance').value = '';
            document.getElementById('cashRemarks').value = '';
        }

        function closeAddCashModal() {
            document.getElementById('addCashModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function saveCashPosition() {
            var accountId = document.getElementById('cashAccount').value;
            var period = document.getElementById('cashPeriod').value;
            var balance = parseFloat(document.getElementById('cashBalance').value) || 0;
            var remarks = document.getElementById('cashRemarks').value.trim();

            if (!accountId || !period || balance < 0) {
                showError('Please fill in all required fields.');
                return;
            }

            var payload = {
                account_id: parseInt(accountId),
                period_month: period + '-01',
                balance_amount: balance,
                remarks: remarks || null
            };

            apiFetch('/cash-positions', { method: 'POST', body: JSON.stringify(payload) })
                .then(function() {
                    closeAddCashModal();
                    showSuccess('Cash position added successfully!');
                    loadCashAsset();
                })
                .catch(function(error) { showError(error.message); });
        }

        // ─── ADD REPAIR ───────────────────────────────────────────────
        function openAddRepairModal() {
            document.getElementById('addRepairModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('repairAssetSelect').value = '';
            document.getElementById('repairExpenseType').value = 'repair';
            document.getElementById('repairAmount').value = '';
            document.getElementById('repairDate').value = '{{ date("Y-m-d") }}';
            document.getElementById('repairRemarks').value = '';
        }

        function closeAddRepairModal() {
            document.getElementById('addRepairModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function saveRepair() {
            var assetId = document.getElementById('repairAssetSelect').value;
            var expenseType = document.getElementById('repairExpenseType').value;
            var amount = parseFloat(document.getElementById('repairAmount').value) || 0;
            var date = document.getElementById('repairDate').value;
            var remarks = document.getElementById('repairRemarks').value.trim();

            if (!assetId || !expenseType || !amount || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            if (amount <= 0) { showError('Amount must be greater than 0.'); return; }

            var payload = {
                asset_id: parseInt(assetId),
                project_id: null,
                expense_type: expenseType,
                amount: amount,
                expense_date: date,
                remarks: remarks || null
            };

            apiFetch('/equipment-expenses', { method: 'POST', body: JSON.stringify(payload) })
                .then(function() {
                    closeAddRepairModal();
                    showSuccess('Repair added successfully!');
                    loadRepair();
                })
                .catch(function(error) { showError(error.message); });
        }

        // ─── ADD BACKHOE EXPENSE ──────────────────────────────────────
        function openAddBackhoeExpenseModal() {
            document.getElementById('addBackhoeExpenseModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('backhoeExpenseAsset').value = '';
            document.getElementById('backhoeExpenseProject').value = '';
            document.getElementById('backhoeExpenseType').value = 'gas_diesel';
            document.getElementById('backhoeExpenseAmount').value = '';
            document.getElementById('backhoeExpenseDate').value = '{{ date("Y-m-d") }}';
            document.getElementById('backhoeExpenseRemarks').value = '';
        }

        function closeAddBackhoeExpenseModal() {
            document.getElementById('addBackhoeExpenseModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function saveBackhoeExpense() {
            var assetId = document.getElementById('backhoeExpenseAsset').value;
            var projectId = document.getElementById('backhoeExpenseProject').value;
            var expenseType = document.getElementById('backhoeExpenseType').value;
            var amount = parseFloat(document.getElementById('backhoeExpenseAmount').value) || 0;
            var date = document.getElementById('backhoeExpenseDate').value;
            var remarks = document.getElementById('backhoeExpenseRemarks').value.trim();

            if (!assetId || !expenseType || !amount || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            if (amount <= 0) { showError('Amount must be greater than 0.'); return; }

            var payload = {
                asset_id: parseInt(assetId),
                project_id: projectId || null,
                expense_type: expenseType,
                amount: amount,
                expense_date: date,
                remarks: remarks || null
            };

            apiFetch('/equipment-expenses', { method: 'POST', body: JSON.stringify(payload) })
                .then(function() {
                    closeAddBackhoeExpenseModal();
                    showSuccess('Backhoe expense added successfully!');
                    loadBackhoe();
                })
                .catch(function(error) { showError(error.message); });
        }

        // ─── ADD BACKHOE RENTAL ──────────────────────────────────────
        function openAddBackhoeRentalModal() {
            document.getElementById('addBackhoeRentalModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('backhoeRentalAsset').value = '';
            document.getElementById('backhoeRentalProject').value = '';
            document.getElementById('backhoeRentalPeriod').value = '{{ date("Y-m") }}';
            document.getElementById('backhoeRentalAmount').value = '';
            document.getElementById('backhoeRentalRemarks').value = '';
        }

        function closeAddBackhoeRentalModal() {
            document.getElementById('addBackhoeRentalModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function saveBackhoeRental() {
            var assetId = document.getElementById('backhoeRentalAsset').value;
            var projectId = document.getElementById('backhoeRentalProject').value;
            var period = document.getElementById('backhoeRentalPeriod').value;
            var amount = parseFloat(document.getElementById('backhoeRentalAmount').value) || 0;
            var remarks = document.getElementById('backhoeRentalRemarks').value.trim();

            if (!assetId || !period || amount <= 0) {
                showError('Please fill in all required fields.');
                return;
            }

            var payload = {
                asset_id: parseInt(assetId),
                project_id: projectId || null,
                period_month: period + '-01',
                amount: amount,
                remarks: remarks || null
            };

            apiFetch('/equipment-rental-income', { method: 'POST', body: JSON.stringify(payload) })
                .then(function() {
                    closeAddBackhoeRentalModal();
                    showSuccess('Rental income added successfully!');
                    loadBackhoe();
                })
                .catch(function(error) { showError(error.message); });
        }

        // ─── ADD BOND ──────────────────────────────────────────────────
        function openAddBondModal() {
            document.getElementById('addBondModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('bondProject').value = '';
            document.getElementById('bondDate').value = '{{ date("Y-m-d") }}';
            document.getElementById('bondAmount').value = '';
            document.getElementById('bondProvider').value = '';
            document.getElementById('bondStatus').value = 'active';
            document.getElementById('bondRemarks').value = '';
        }

        function closeAddBondModal() {
            document.getElementById('addBondModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function saveBond() {
            var projectId = document.getElementById('bondProject').value;
            var date = document.getElementById('bondDate').value;
            var amount = parseFloat(document.getElementById('bondAmount').value) || 0;
            var provider = document.getElementById('bondProvider').value.trim();
            var status = document.getElementById('bondStatus').value;
            var remarks = document.getElementById('bondRemarks').value.trim();

            if (!projectId || !date || !amount || amount <= 0) {
                showError('Please fill in all required fields.');
                return;
            }

            var payload = {
                project_id: parseInt(projectId),
                bond_date: date,
                amount: amount,
                bond_provider: provider || null,
                status: status,
                remarks: remarks || null
            };

            apiFetch('/construction-bonds', { method: 'POST', body: JSON.stringify(payload) })
                .then(function() {
                    closeAddBondModal();
                    showSuccess('Bond added successfully!');
                    loadBonds();
                })
                .catch(function(error) { showError(error.message); });
        }

        function deleteBond(id) {
            if (!confirm('Are you sure you want to delete this bond?')) return;
            apiFetch('/construction-bonds/' + id, { method: 'DELETE' })
                .then(function() {
                    showSuccess('Bond deleted successfully!');
                    loadBonds();
                })
                .catch(function(error) { showError(error.message); });
        }

        // ─── REPORTS ───────────────────────────────────────────────────

        // EXPOVRALL
        function loadExpovrall() {
            var month = document.getElementById('expovrallMonth').value;
            if (!month) return;
            var period = month + '-01';

            apiFetch('/reports/expovrall?period=' + period)
                .then(function(data) {
                    var tbody = document.getElementById('expovrallBody');
                    tbody.innerHTML = '';

                    if (!data || !data.length) {
                        tbody.innerHTML = '<tr><td colspan="14" style="text-align:center;padding:20px;">No data found for this period.</td></tr>';
                        return;
                    }

                    var projects = {};
                    var categories = EXPOVRALL_CATEGORIES;

                    data.forEach(function(row) {
                        if (!projects[row.project_name]) {
                            projects[row.project_name] = {};
                            categories.forEach(function(cat) { projects[row.project_name][cat] = 0; });
                            projects[row.project_name]['_total'] = 0;
                            projects[row.project_name]['_project_id'] = row.project_id;
                        }
                        if (categories.indexOf(row.category_code) !== -1) {
                            projects[row.project_name][row.category_code] = parseFloat(row.category_total) || 0;
                            projects[row.project_name]['_total'] += parseFloat(row.category_total) || 0;
                        }
                    });

                    var grandTotal = 0;
                    var categoryTotals = {};
                    categories.forEach(function(cat) { categoryTotals[cat] = 0; });
                    var totalA = 0;
                    var totalB = 0;

                    Object.keys(projects).forEach(function(projectName) {
                        var p = projects[projectName];
                        var tr = document.createElement('tr');
                        var cells = '<td><strong>' + projectName + '</strong></td>';
                        categories.forEach(function(cat) {
                            var val = p[cat] || 0;
                            cells += '<td>' + formatCurrency(val) + '</td>';
                            categoryTotals[cat] += val;
                            if (cat !== 'RENT' && cat !== 'STATIONERY' && cat !== 'DEPRECIATION' && cat !== 'REPAIR_MAINT' && cat !== 'SSS_PHILHEALTH') {
                                totalA += val;
                            } else {
                                totalB += val;
                            }
                        });
                        cells += '<td><strong>' + formatCurrency(p['_total']) + '</strong></td>';
                        tr.innerHTML = cells;
                        tbody.appendChild(tr);
                        grandTotal += p['_total'];
                    });

                    var tr = document.createElement('tr');
                    tr.className = 'total-row';
                    var cells = '<td><strong>TOTAL</strong></td>';
                    categories.forEach(function(cat) {
                        cells += '<td><strong>' + formatCurrency(categoryTotals[cat]) + '</strong></td>';
                    });
                    cells += '<td><strong>' + formatCurrency(grandTotal) + '</strong></td>';
                    tr.innerHTML = cells;
                    tbody.appendChild(tr);

                    var formulaRow = document.createElement('tr');
                    formulaRow.className = 'sub-total-row';
                    var netDirect = totalA - totalB;
                    // formulaRow.innerHTML = '<td><strong>A - B (Net Direct)</strong></td>' +
                    //     '<td colspan="' + (categories.length) + '" style="text-align:right;font-size:1.1rem;">' +
                    //     'A = ' + formatCurrency(totalA) + ' | B = ' + formatCurrency(totalB) + ' | Net Direct = ' + formatCurrency(netDirect) +
                    //     '</td><td></td>';
                    // tbody.appendChild(formulaRow);
                })
                .catch(function(error) {
                    showError('Error loading EXPOVRALL: ' + error.message);
                });
        }

        // EXP DIRECT
        function loadExpDirect() {
            var month = document.getElementById('expdirectMonth').value;
            if (!month) return;
            var period = month + '-01';

            Promise.all([
                apiFetch('/reports/expovrall?period=' + period),
                apiFetch('/reports/admin-expense?period=' + period)
            ])
            .then(function(results) {
                var data = results[0] || [];
                var adminData = results[1] || [];
                var tbody = document.getElementById('expdirectBody');
                tbody.innerHTML = '';

                if (!data || !data.length) {
                    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:20px;">No data found.</td></tr>';
                    return;
                }

                var projects = {};
                var categories = EXP_DIRECT_CATEGORIES;

                data.forEach(function(row) {
                    if (!projects[row.project_name]) {
                        projects[row.project_name] = {};
                        categories.forEach(function(cat) { projects[row.project_name][cat] = 0; });
                        projects[row.project_name]['_total'] = 0;
                        projects[row.project_name]['_project_id'] = row.project_id;
                    }
                    if (categories.indexOf(row.category_code) !== -1) {
                        projects[row.project_name][row.category_code] = parseFloat(row.category_total) || 0;
                        projects[row.project_name]['_total'] += parseFloat(row.category_total) || 0;
                    }
                });

                adminData.forEach(function(row) {
                    if (projects[row.project_name]) {
                        projects[row.project_name]['_admin'] = parseFloat(row.r_total) || 0;
                    } else if (row.project_name === 'OFFICE') {
                        if (!projects['OFFICE']) {
                            projects['OFFICE'] = {};
                            categories.forEach(function(cat) { projects['OFFICE'][cat] = 0; });
                            projects['OFFICE']['_total'] = 0;
                            projects['OFFICE']['_admin'] = 0;
                        }
                        projects['OFFICE']['_admin'] = parseFloat(row.r_total) || 0;
                    }
                });

                var totalAdmin = 0;
                Object.keys(projects).forEach(function(projectName) {
                    var p = projects[projectName];
                    var tr = document.createElement('tr');
                    var cells = '<td><strong>' + projectName + '</strong></td>';
                    categories.forEach(function(cat) {
                        cells += '<td>' + formatCurrency(p[cat] || 0) + '</td>';
                    });
                    var admin = p['_admin'] || 0;
                    totalAdmin += admin;
                    cells += '<td><strong>' + formatCurrency(p['_total']) + '</strong></td>';
                    cells += '<td>' + formatCurrency(admin) + '</td>';
                    tr.innerHTML = cells;
                    tbody.appendChild(tr);
                });

                var tr = document.createElement('tr');
                tr.className = 'total-row';
                tr.innerHTML = '<td><strong>TOTAL</strong></td>' +
                    '<td colspan="7"></td>' +
                    '<td><strong>' + formatCurrency(Object.keys(projects).reduce(function(sum, name) { return sum + projects[name]['_total']; }, 0)) + '</strong></td>' +
                    '<td><strong>' + formatCurrency(totalAdmin) + '</strong></td>';
                tbody.appendChild(tr);
            })
            .catch(function(error) {
                showError('Error loading EXP DIRECT: ' + error.message);
            });
        }

        // ADMIN EXP
        function loadAdminExp() {
            var month = document.getElementById('adminexpMonth').value;
            if (!month) return;
            var period = month + '-01';

            apiFetch('/reports/admin-expense?period=' + period)
                .then(function(data) {
                    var tbody = document.getElementById('adminexpBody');
                    tbody.innerHTML = '';

                    if (!data || !data.length) {
                        tbody.innerHTML = '<tr><td colspan="15" style="text-align:center;padding:20px;">No data found.</td></tr>';
                        return;
                    }

                    var projects = {};
                    var categories = ADMIN_EXP_CATEGORIES;

                    data.forEach(function(row) {
                        if (!projects[row.project_name]) {
                            projects[row.project_name] = {};
                            categories.forEach(function(cat) { projects[row.project_name][cat] = 0; });
                            projects[row.project_name]['_total'] = 0;
                        }
                        if (categories.indexOf(row.category_code) !== -1) {
                            projects[row.project_name][row.category_code] = parseFloat(row.r_total) || 0;
                            projects[row.project_name]['_total'] += parseFloat(row.r_total) || 0;
                        }
                    });

                    var grandTotal = 0;
                    var categoryTotals = {};
                    categories.forEach(function(cat) { categoryTotals[cat] = 0; });

                    Object.keys(projects).forEach(function(projectName) {
                        var p = projects[projectName];
                        var tr = document.createElement('tr');
                        var cells = '<td><strong>' + projectName + '</strong></td>';
                        categories.forEach(function(cat) {
                            var val = p[cat] || 0;
                            cells += '<td>' + formatCurrency(val) + '</td>';
                            categoryTotals[cat] += val;
                        });
                        cells += '<td><strong>' + formatCurrency(p['_total']) + '</strong></td>';
                        tr.innerHTML = cells;
                        tbody.appendChild(tr);
                        grandTotal += p['_total'];
                    });

                    var tr = document.createElement('tr');
                    tr.className = 'total-row';
                    var cells = '<td><strong>TOTAL ADMIN EXPENSES</strong></td>';
                    categories.forEach(function(cat) {
                        cells += '<td><strong>' + formatCurrency(categoryTotals[cat]) + '</strong></td>';
                    });
                    cells += '<td><strong>' + formatCurrency(grandTotal) + '</strong></td>';
                    tr.innerHTML = cells;
                    tbody.appendChild(tr);
                })
                .catch(function(error) {
                    showError('Error loading ADMIN EXP: ' + error.message);
                });
        }

        // DIRECT EXP
        function loadDirectExp() {
            var month = document.getElementById('directexpMonth').value;
            if (!month) return;
            var period = month + '-01';

            Promise.all([
                apiFetch('/reports/expovrall?period=' + period),
                apiFetch('/reports/admin-expense?period=' + period)
            ])
            .then(function(results) {
                var data = results[0] || [];
                var adminData = results[1] || [];
                var tbody = document.getElementById('directexpBody');
                tbody.innerHTML = '';

                if (!data || !data.length) {
                    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:20px;">No data found.</td></tr>';
                    return;
                }

                var projects = {};
                var categories = EXP_DIRECT_CATEGORIES;

                data.forEach(function(row) {
                    if (!projects[row.project_name]) {
                        projects[row.project_name] = {};
                        categories.forEach(function(cat) { projects[row.project_name][cat] = 0; });
                        projects[row.project_name]['_total'] = 0;
                        projects[row.project_name]['_project_id'] = row.project_id;
                    }
                    if (categories.indexOf(row.category_code) !== -1) {
                        projects[row.project_name][row.category_code] = parseFloat(row.category_total) || 0;
                        projects[row.project_name]['_total'] += parseFloat(row.category_total) || 0;
                    }
                });

                adminData.forEach(function(row) {
                    if (projects[row.project_name]) {
                        projects[row.project_name]['_admin'] = parseFloat(row.r_total) || 0;
                    } else if (row.project_name === 'OFFICE') {
                        if (!projects['OFFICE']) {
                            projects['OFFICE'] = {};
                            categories.forEach(function(cat) { projects['OFFICE'][cat] = 0; });
                            projects['OFFICE']['_total'] = 0;
                            projects['OFFICE']['_admin'] = 0;
                        }
                        projects['OFFICE']['_admin'] = parseFloat(row.r_total) || 0;
                    }
                });

                var totalAdmin = 0;
                Object.keys(projects).forEach(function(projectName) {
                    var p = projects[projectName];
                    var tr = document.createElement('tr');
                    var cells = '<td><strong>' + projectName + '</strong></td>';
                    categories.forEach(function(cat) {
                        cells += '<td>' + formatCurrency(p[cat] || 0) + '</td>';
                    });
                    var admin = p['_admin'] || 0;
                    totalAdmin += admin;
                    cells += '<td><strong>' + formatCurrency(p['_total']) + '</strong></td>';
                    cells += '<td>' + formatCurrency(admin) + '</td>';
                    tr.innerHTML = cells;
                    tbody.appendChild(tr);
                });

                var tr = document.createElement('tr');
                tr.className = 'total-row';
                tr.innerHTML = '<td><strong>TOTAL</strong></td>' +
                    '<td colspan="7"></td>' +
                    '<td><strong>' + formatCurrency(Object.keys(projects).reduce(function(sum, name) { return sum + projects[name]['_total']; }, 0)) + '</strong></td>' +
                    '<td><strong>' + formatCurrency(totalAdmin) + '</strong></td>';
                tbody.appendChild(tr);

                var currentTotal = Object.keys(projects).reduce(function(sum, name) { return sum + projects[name]['_total']; }, 0);
                var prevDate = new Date(period);
                prevDate.setMonth(prevDate.getMonth() - 1);
                var prevPeriod = prevDate.getFullYear() + '-' + String(prevDate.getMonth() + 1).padStart(2, '0') + '-01';

                apiFetch('/reports/expovrall?period=' + prevPeriod)
                    .then(function(prevData) {
                        var prevProjects = {};
                        prevData.forEach(function(row) {
                            if (!prevProjects[row.project_name]) {
                                prevProjects[row.project_name] = {};
                                categories.forEach(function(cat) { prevProjects[row.project_name][cat] = 0; });
                                prevProjects[row.project_name]['_total'] = 0;
                            }
                            if (categories.indexOf(row.category_code) !== -1) {
                                prevProjects[row.project_name][row.category_code] = parseFloat(row.category_total) || 0;
                                prevProjects[row.project_name]['_total'] += parseFloat(row.category_total) || 0;
                            }
                        });
                        var prevTotal = Object.keys(prevProjects).reduce(function(sum, name) { return sum + prevProjects[name]['_total']; }, 0);
                        var net = currentTotal - prevTotal;

                        var netRow = document.createElement('tr');
                        netRow.className = 'sub-total-row';
                        // netRow.innerHTML = '<td><strong>NET (Current - Previous)</strong></td>' +
                        //     '<td colspan="7" style="text-align:right;">' +
                        //     'Current: ' + formatCurrency(currentTotal) + ' | Previous: ' + formatCurrency(prevTotal) +
                        //     '</td><td><strong>' + formatCurrency(net) + '</strong></td><td></td>';
                        // tbody.appendChild(netRow);
                    })
                    .catch(function() { /* ignore prev month error */ });
            })
            .catch(function(error) {
                showError('Error loading DIRECT EXP: ' + error.message);
            });
        }

        // OVERALL EXP
        function loadOverallExp() {
            var month = document.getElementById('overallexpMonth').value;
            if (!month) return;
            var period = month + '-01';

            Promise.all([
                apiFetch('/reports/expovrall?period=' + period),
                apiFetch('/reports/admin-expense?period=' + period)
            ])
            .then(function(results) {
                var data = results[0] || [];
                var adminData = results[1] || [];
                var tbody = document.getElementById('overallexpBody');
                tbody.innerHTML = '';

                if (!data || !data.length) {
                    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:20px;">No data found.</td></tr>';
                    return;
                }

                var projects = {};
                var categories = EXP_DIRECT_CATEGORIES;

                data.forEach(function(row) {
                    if (!projects[row.project_name]) {
                        projects[row.project_name] = {};
                        categories.forEach(function(cat) { projects[row.project_name][cat] = 0; });
                        projects[row.project_name]['_total'] = 0;
                        projects[row.project_name]['_project_id'] = row.project_id;
                    }
                    if (categories.indexOf(row.category_code) !== -1) {
                        projects[row.project_name][row.category_code] = parseFloat(row.category_total) || 0;
                        projects[row.project_name]['_total'] += parseFloat(row.category_total) || 0;
                    }
                });

                adminData.forEach(function(row) {
                    if (projects[row.project_name]) {
                        projects[row.project_name]['_admin'] = parseFloat(row.r_total) || 0;
                    }
                });

                var totalAdmin = 0;
                Object.keys(projects).forEach(function(projectName) {
                    var p = projects[projectName];
                    var tr = document.createElement('tr');
                    var cells = '<td><strong>' + projectName + '</strong></td>';
                    categories.forEach(function(cat) {
                        cells += '<td>' + formatCurrency(p[cat] || 0) + '</td>';
                    });
                    var admin = p['_admin'] || 0;
                    totalAdmin += admin;
                    cells += '<td>' + formatCurrency(admin) + '</td>';
                    var overallTotal = p['_total'] + admin;
                    cells += '<td><strong>' + formatCurrency(overallTotal) + '</strong></td>';
                    tr.innerHTML = cells;
                    tbody.appendChild(tr);
                });

                var grandTotal = Object.keys(projects).reduce(function(sum, name) {
                    return sum + projects[name]['_total'] + (projects[name]['_admin'] || 0);
                }, 0);

                var tr = document.createElement('tr');
                tr.className = 'total-row';
                tr.innerHTML = '<td><strong>TOTAL OVERALL EXPENSES</strong></td>' +
                    '<td colspan="7"></td>' +
                    '<td><strong>' + formatCurrency(totalAdmin) + '</strong></td>' +
                    '<td><strong>' + formatCurrency(grandTotal) + '</strong></td>';
                tbody.appendChild(tr);
            })
            .catch(function(error) {
                showError('Error loading OVERALL EXP: ' + error.message);
            });
        }

        // PROFIT/LOSS
        function loadProfit() {
            var type = document.getElementById('profitType').value;
            var endpoint = type === 'direct' ? '/reports/profit-direct' : '/reports/profit-overall';

            apiFetch(endpoint)
                .then(function(data) {
                    var tbody = document.getElementById('profitBody');
                    tbody.innerHTML = '';

                    if (!data || !data.length) {
                        tbody.innerHTML = '<tr><td colspan="13" style="text-align:center;padding:20px;">No data found.</td></tr>';
                        return;
                    }

                    data.forEach(function(row) {
                        var tr = document.createElement('tr');
                        var profitPayment = parseFloat(row.profit_loss_payment_basis) || 0;
                        var profitContract = parseFloat(row.profit_loss_contract_basis) || 0;
                        tr.setAttribute('data-project-id', row.project_id);
                        tr.setAttribute('data-contract-id', row.contract_id || '');
                        tr.setAttribute('data-contract-price', row.original_contract_price || 0);
                        tr.setAttribute('data-addl-works', row.additional_works_contract || 0);
                        tr.setAttribute('data-payment', row.original_payment_received || 0);
                        tr.setAttribute('data-addl-payment', row.additional_works_payment || 0);
                        tr.setAttribute('data-remarks', row.remarks || '');
                        tr.innerHTML = '<td><strong>' + (row.project_name || '') + '</strong></td>' +
                            '<td>' + (row.start_date || '') + '</td>' +
                            '<td>' + (row.actual_end_date || 'In Progress') + '</td>' +
                            '<td>' + formatCurrency(row.original_contract_price || 0) + '</td>' +
                            '<td>' + formatCurrency(row.additional_works_contract || 0) + '</td>' +
                            '<td>' + formatCurrency(row.total_contract_price) + '</td>' +
                            '<td>' + formatCurrency(row.original_payment_received || 0) + '</td>' +
                            '<td>' + formatCurrency(row.additional_works_payment || 0) + '</td>' +
                            '<td>' + formatCurrency(row.total_payment) + '</td>' +
                            '<td>' + formatCurrency(row.project_expense) + '</td>' +
                            '<td>' + formatCurrency(row.accounts_receivable) + '</td>' +
                            '<td class="' + (profitPayment < 0 ? 'amount-negative' : 'amount-positive') + '">' + formatCurrency(profitPayment) + '</td>' +
                            '<td class="' + (profitContract < 0 ? 'amount-negative' : 'amount-positive') + '">' + formatCurrency(profitContract) + '</td>';
                        tbody.appendChild(tr);
                    });
                })
                .catch(function(error) {
                    showError('Error loading Profit/Loss: ' + error.message);
                });
        }

        // RECEIVABLES/PAYABLES
        function loadReceivables() {
            var type = document.getElementById('receivableType').value;

            apiFetch('/reports/receivable-payable?entry_type=' + type)
                .then(function(data) {
                    var tbody = document.getElementById('receivableBody');
                    tbody.innerHTML = '';

                    if (!data || !data.length) {
                        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:20px;">No data found.</td></tr>';
                        return;
                    }

                    var total = 0;
                    data.forEach(function(row) {
                        var tr = document.createElement('tr');
                        var rTotal = parseFloat(row.r_total) || 0;
                        tr.innerHTML = '<td>' + (row.entry_date || '') + '</td>' +
                            '<td>' + (row.counterparty_name || '') + '</td>' +
                            '<td>' + (row.project_id || '—') + '</td>' +
                            '<td>' + formatCurrency(row.amount_30d || 0) + '</td>' +
                            '<td>' + formatCurrency(row.amount_31_60d || 0) + '</td>' +
                            '<td>' + formatCurrency(row.amount_61_90d || 0) + '</td>' +
                            '<td>' + formatCurrency(row.amount_91_120d || 0) + '</td>' +
                            '<td><strong>' + formatCurrency(rTotal) + '</strong></td>' +
                            '<td><span class="' + (row.status === 'settled' ? 'status-released' : 'status-active') + '">' + (row.status || 'outstanding') + '</span></td>';
                        tbody.appendChild(tr);
                        total += rTotal;
                    });

                    var tr = document.createElement('tr');
                    tr.className = 'total-row';
                    tr.innerHTML = '<td colspan="7"><strong>GRAND TOTAL</strong></td><td><strong>' + formatCurrency(total) + '</strong></td><td></td>';
                    tbody.appendChild(tr);
                })
                .catch(function(error) {
                    showError('Error loading Receivables/Payables: ' + error.message);
                });
        }

        // CASH ASSET
        function loadCashAsset() {
            var month = document.getElementById('cashMonth').value;
            if (!month) return;
            var period = month + '-01';

            apiFetch('/reports/cash-asset?period=' + period)
                .then(function(data) {
                    var tbody = document.getElementById('cashBody');
                    tbody.innerHTML = '';

                    if (!data || !data.length) {
                        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:20px;">No data found for this period.</td></tr>';
                        return;
                    }

                    var total = 0;
                    data.forEach(function(row) {
                        var tr = document.createElement('tr');
                        var accountName = row.account_name || 'Account ' + row.account_id;
                        var accountType = row.account_type || 'treasury';
                        tr.innerHTML = '<td><strong>' + accountName + '</strong></td>' +
                            '<td>' + accountType.replace('_', ' ').toUpperCase() + '</td>' +
                            '<td>' + formatCurrency(row.balance_amount || 0) + '</td>';
                        tbody.appendChild(tr);
                        total += parseFloat(row.balance_amount) || 0;
                    });

                    var tr = document.createElement('tr');
                    tr.className = 'total-row';
                    tr.innerHTML = '<td colspan="2"><strong>TOTAL CASH ASSET</strong></td><td><strong>' + formatCurrency(total) + '</strong></td>';
                    tbody.appendChild(tr);
                })
                .catch(function(error) {
                    showError('Error loading Cash Asset: ' + error.message);
                });
        }

        // REPAIR
        function loadRepair() {
            var month = document.getElementById('repairMonth').value;
            if (!month) return;
            var period = month + '-01';
            var assetFilter = document.getElementById('repairAsset').value;

            var url = '/reports/repair-total?period=' + period;

            apiFetch(url)
                .then(function(data) {
                    var tbody = document.getElementById('repairBody');
                    tbody.innerHTML = '';

                    if (!data || !data.length) {
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;">No data found.</td></tr>';
                        return;
                    }

                    data.forEach(function(row) {
                        var tr = document.createElement('tr');
                        var expenseType = row.expense_type || 'repair';
                        var expenseTypeLabel = expenseType.replace('_', ' ').toUpperCase();
                        tr.innerHTML = '<td><strong>' + (row.asset_name || 'Unknown Asset') + '</strong></td>' +
                            '<td>' + expenseTypeLabel + '</td>' +
                            '<td>' + formatCurrency(row.asset_total || row.amount || 0) + '</td>' +
                            '<td>' + (row.period_month || row.expense_date || '') + '</td>' +
                            '<td>' + (row.remarks || '—') + '</td>';
                        tbody.appendChild(tr);
                    });
                })
                .catch(function(error) {
                    showError('Error loading Repair data: ' + error.message);
                });
        }

        // BACKHOE
        function loadBackhoe() {
            var assetId = document.getElementById('backhoeAsset').value;
            var url = '/reports/backhoe-profitability' + (assetId ? '?asset_id=' + assetId : '');

            apiFetch(url)
                .then(function(data) {
                    var tbody = document.getElementById('backhoeBody');
                    tbody.innerHTML = '';

                    if (!data || !data.length) {
                        tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;padding:20px;">No data found.</td></tr>';
                        return;
                    }

                    var assetTotals = {};
                    data.forEach(function(row) {
                        var assetName = row.asset_name || 'Unknown';
                        if (!assetTotals[assetName]) {
                            assetTotals[assetName] = {
                                total_expense: 0,
                                rental_income: 0,
                                net_income: 0,
                                periods: []
                            };
                        }
                        var period = row.period_month || 'Unknown';
                        if (assetTotals[assetName].periods.indexOf(period) === -1) {
                            assetTotals[assetName].periods.push(period);
                        }
                        var expense = parseFloat(row.total_expense) || 0;
                        var income = parseFloat(row.rental_income) || 0;
                        assetTotals[assetName].total_expense += expense;
                        assetTotals[assetName].rental_income += income;
                        assetTotals[assetName].net_income += (income - expense);
                    });

                    var grandExpense = 0;
                    var grandIncome = 0;
                    var grandNet = 0;

                    Object.keys(assetTotals).forEach(function(assetName) {
                        var a = assetTotals[assetName];
                        var tr = document.createElement('tr');
                        var net = a.net_income;
                        tr.innerHTML = '<td><strong>' + assetName + '</strong></td>' +
                            '<td>' + a.periods.join(', ') + '</td>' +
                            '<td>' + formatCurrency(0) + '</td>' +
                            '<td>' + formatCurrency(0) + '</td>' +
                            '<td>' + formatCurrency(0) + '</td>' +
                            '<td>' + formatCurrency(0) + '</td>' +
                            '<td>' + formatCurrency(0) + '</td>' +
                            '<td>' + formatCurrency(0) + '</td>' +
                            '<td><strong>' + formatCurrency(a.total_expense) + '</strong></td>' +
                            '<td>' + formatCurrency(a.rental_income) + '</td>' +
                            '<td class="' + (net < 0 ? 'amount-negative' : 'amount-positive') + '">' + formatCurrency(net) + '</td>';
                        tbody.appendChild(tr);
                        grandExpense += a.total_expense;
                        grandIncome += a.rental_income;
                        grandNet += a.net_income;
                    });

                    var tr = document.createElement('tr');
                    tr.className = 'total-row';
                    tr.innerHTML = '<td colspan="8"><strong>GRAND TOTAL</strong></td>' +
                        '<td><strong>' + formatCurrency(grandExpense) + '</strong></td>' +
                        '<td><strong>' + formatCurrency(grandIncome) + '</strong></td>' +
                        '<td class="' + (grandNet < 0 ? 'amount-negative' : 'amount-positive') + '"><strong>' + formatCurrency(grandNet) + '</strong></td>';
                    tbody.appendChild(tr);
                })
                .catch(function(error) {
                    showError('Error loading Backhoe data: ' + error.message);
                });
        }

        // BONDS
        function loadBonds() {
            var projectFilter = document.getElementById('bondProjectFilter').value;
            var statusFilter = document.getElementById('bondStatusFilter').value;
            
            var endpoint = '/construction-bonds';
            var params = [];
            
            if (projectFilter && projectFilter !== 'all') {
                params.push('project_id=' + projectFilter);
            }
            if (statusFilter && statusFilter !== 'all') {
                params.push('status=' + statusFilter);
            }
            
            if (params.length > 0) {
                endpoint += '?' + params.join('&');
            }
            
            apiFetch(endpoint)
                .then(function(data) {
                    var tbody = document.getElementById('bondBody');
                    tbody.innerHTML = '';

                    if (!data || !data.length) {
                        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;">No bonds found.</td></tr>';
                        return;
                    }

                    var total = 0;
                    data.forEach(function(row) {
                        var tr = document.createElement('tr');
                        var statusClass = 'status-' + (row.status || 'active');
                        tr.innerHTML = '<td>' + (row.bond_date || '') + '</td>' +
                            '<td>' + (row.project_name || 'Project ' + row.project_id) + '</td>' +
                            '<td>' + (row.bond_provider || '—') + '</td>' +
                            '<td>' + formatCurrency(row.amount || 0) + '</td>' +
                            '<td><span class="' + statusClass + '">' + (row.status || 'active') + '</span></td>' +
                            '<td><button onclick="deleteBond(' + row.bond_id + ')" style="background:none;border:none;color:#d32f2f;cursor:pointer;">✕</button></td>';
                        tbody.appendChild(tr);
                        total += parseFloat(row.amount) || 0;
                    });

                    var tr = document.createElement('tr');
                    tr.className = 'total-row';
                    tr.innerHTML = '<td colspan="3"><strong>GRAND TOTAL</strong></td>' +
                        '<td><strong>' + formatCurrency(total) + '</strong></td>' +
                        '<td colspan="2"></td>';
                    tbody.appendChild(tr);
                })
                .catch(function(error) {
                    showError('Error loading Bonds: ' + error.message);
                });
        }

        // SUMMARY - Shows total per month across all projects
        function loadSummary() {
            var month = document.getElementById('summaryMonth').value;
            if (!month) return;
            var period = month + '-01';

            // Get summary from EXPOVRALL for the selected month
            apiFetch('/reports/expovrall?period=' + period)
                .then(function(data) {
                    var tbody = document.getElementById('summaryBody');
                    tbody.innerHTML = '';

                    if (!data || !data.length) {
                        tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;padding:20px;">No data found for this period.</td></tr>';
                        return;
                    }

                    var categories = {
                        'Salaries and Wages Expense': 0,
                        "Employer's Contribution (SSS, Philhealth)": 0,
                        'Delivery Expense': 0,
                        'Permit, Taxes and Licenses': 0,
                        'Rent Expense': 0,
                        'Utilities Expense': 0,
                        'Stationary Expense': 0,
                        'Repair and Maintenance': 0,
                        'Construction Supply Exp.': 0,
                        'Transportation Expense': 0,
                        'Depreciation Expense': 0,
                        'Other Expense': 0
                    };

                    var categoryMapping = {
                        'SALARIES_WAGES': 'Salaries and Wages Expense',
                        'SSS_PHILHEALTH': "Employer's Contribution (SSS, Philhealth)",
                        'DELIVERY': 'Delivery Expense',
                        'PERMIT_TAXES_LICENSES': 'Permit, Taxes and Licenses',
                        'RENT': 'Rent Expense',
                        'UTILITIES': 'Utilities Expense',
                        'STATIONERY': 'Stationary Expense',
                        'REPAIR_MAINT': 'Repair and Maintenance',
                        'CONST_SUPPLY': 'Construction Supply Exp.',
                        'TRANSPO': 'Transportation Expense',
                        'DEPRECIATION': 'Depreciation Expense',
                        'OTHERS': 'Other Expense'
                    };

                    // Aggregate all projects for the selected month
                    data.forEach(function(row) {
                        if (categoryMapping[row.category_code]) {
                            categories[categoryMapping[row.category_code]] += parseFloat(row.category_total) || 0;
                        }
                    });

                    var total = 0;
                    Object.keys(categories).forEach(function(catName) {
                        var tr = document.createElement('tr');
                        var val = categories[catName];
                        tr.innerHTML = '<td>' + catName + '</td><td>' + formatCurrency(val) + '</td>';
                        tbody.appendChild(tr);
                        total += val;
                    });

                    var tr = document.createElement('tr');
                    tr.className = 'total-row';
                    tr.innerHTML = '<td><strong>TOTAL EXPENSES</strong></td><td><strong>' + formatCurrency(total) + '</strong></td>';
                    tbody.appendChild(tr);
                })
                .catch(function(error) {
                    showError('Error loading Summary: ' + error.message);
                });
        }

        // ─── EVENT LISTENERS ──────────────────────────────────────────
        document.addEventListener('click', function(e) {
            var modals = ['addExpenseModal', 'addBudgetModal', 'addAdminExpModal', 'addContractModal', 
                          'addReceivableModal', 'addCashModal', 'addRepairModal', 'addBackhoeExpenseModal',
                          'addBackhoeRentalModal', 'addBondModal', 'expenseDetailModal', 'budgetDetailModal'];
            modals.forEach(function(id) {
                var modal = document.getElementById(id);
                if (modal && e.target === modal) {
                    var closeFn = {
                        'addExpenseModal': closeAddExpenseModal,
                        'addBudgetModal': closeAddBudgetModal,
                        'addAdminExpModal': closeAddAdminExpModal,
                        'addContractModal': closeAddContractModal,
                        'addReceivableModal': closeAddReceivableModal,
                        'addCashModal': closeAddCashModal,
                        'addRepairModal': closeAddRepairModal,
                        'addBackhoeExpenseModal': closeAddBackhoeExpenseModal,
                        'addBackhoeRentalModal': closeAddBackhoeRentalModal,
                        'addBondModal': closeAddBondModal,
                        'expenseDetailModal': closeExpenseDetailModal,
                        'budgetDetailModal': closeBudgetDetailModal
                    }[id];
                    if (closeFn) closeFn();
                }
            });

            if (document.getElementById('errorNotification').style.display === 'block') {
                if (!e.target.closest('.error-notification')) closeError();
            }
            if (document.getElementById('successNotification').style.display === 'block') {
                if (!e.target.closest('.success-notification')) closeSuccess();
            }
        });

        // ─── INIT ─────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {
            fetchProjects()
                .then(function() { return fetchExpenseCategories(); })
                .then(function() { return fetchExpenses(); })
                .then(function() { return fetchAssets(); })
                .then(function() { 
                    // Load initial budget data after expenses are loaded
                    if (currentReportTab === 'budgets') {
                        fetchBudgetData();
                    }
                });
            initializeProofFileUpload();
        });
    </script>

</body>
</html>
