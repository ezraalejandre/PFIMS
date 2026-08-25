<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Budget & Finance - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/finance.css') }}">
    <link rel="stylesheet" href="{{ asset('css/module-analytics.css') }}">
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
        .badge-info { background: #e3f2fd; color: #1565c0; }

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
            padding: 8px 20px;
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
        .btn-add-data.gold {
            background: #c9a96e;
        }
        .btn-add-data.gold:hover {
            background: #b8975a;
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

        /* ─── DYNAMIC EXPENSE FIELDS ─── */
        .dynamic-amount-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            padding: 15px;
            background: #f8f9fb;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            margin-bottom: 10px;
        }
        .dynamic-amount-fields .form-group {
            margin-bottom: 0;
        }
        .dynamic-amount-fields .form-group label {
            font-size: 0.75rem;
            color: #666;
        }

        /* ─── CATEGORY BADGE ─── */
        .category-badge.admin {
            background: #e3f2fd;
            color: #1565c0;
        }
        .category-badge.direct {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .category-badge.labor {
            background: #fff3e0;
            color: #c9a96e;
        }
        .category-badge.material {
            background: #e6f7e6;
            color: #2e7d32;
        }
        .category-badge.equipment {
            background: #e8eaf6;
            color: #3949ab;
        }
        .category-badge.other {
            background: #f5f5f5;
            color: #666;
        }

        /* ─── FILE UPLOAD ─── */
        .file-upload-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
        }
        .file-upload-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            background: #f5f7fa;
            border: 1px solid #d8dee6;
            border-radius: 8px;
            color: #1a2b3c;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s ease;
            white-space: nowrap;
        }
        .file-upload-button:hover {
            background: #e9edf2;
        }
        .file-upload-name {
            color: #666;
            font-size: 0.9rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 250px;
        }
        .file-upload-hint {
            display: block;
            margin-top: 6px;
            color: #888;
            font-size: 0.8rem;
        }

        .expense-file-section {
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid #e9ecef;
        }
        .expense-file-display {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            background: #f8f9fb;
            border: 1px solid #e1e5ea;
            border-radius: 8px;
        }
        .expense-file-info {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .expense-file-icon { font-size: 20px; flex-shrink: 0; }
        .expense-file-name {
            color: #333;
            font-size: 0.9rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .expense-file-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .btn-view-file, .btn-change-file {
            padding: 8px 13px;
            border-radius: 7px;
            border: 1px solid #d8dee6;
            background: #fff;
            color: #1a2b3c;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s ease;
        }
        .btn-view-file:hover, .btn-change-file:hover { background: #f0f3f6; }
        .btn-delete-file {
            padding: 8px 13px;
            border-radius: 7px;
            border: 1px solid #f3c6c6;
            background: #fff;
            color: #d32f2f;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s ease;
        }
        .btn-delete-file:hover { background: #fdeceb; }
        .file-preview-container {
            margin-top: 12px;
            display: none;
            text-align: center;
        }
        .file-preview-container img {
            max-width: 100%;
            max-height: 250px;
            border-radius: 8px;
            border: 1px solid #ddd;
            object-fit: contain;
        }
        .file-input-hidden { display: none; }

        /* ─── RESPONSIVE ─── */
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
            .dynamic-amount-fields { grid-template-columns: 1fr; }
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

    <!-- ─── DELETE CONFIRMATION MODALS ─── -->
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
                <button class="btn-cancel" onclick="closeBudgetDeleteModal()">Cancel</button>
                <button class="btn-delete" id="confirmBudgetDeleteBtn" onclick="confirmBudgetDelete()">Delete</button>
            </div>
        </div>
    </div>

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
                <button class="btn-cancel" onclick="closeBondDeleteModal()">Cancel</button>
                <button class="btn-delete" id="confirmBondDeleteBtn" onclick="confirmBondDelete()">Delete</button>
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
                <li><a href="{{ url('/dashboard') }}">DASHBOARD</a></li>
                <li><a href="{{ url('/projects') }}">PROJECTS</a></li>
                <li class="active"><a href="{{ url('/finance') }}">FINANCE</a></li>
                <li><a href="{{ url('/inventory') }}">INVENTORY</a></li>
                <li><a href="{{ url('/suppliers') }}">SUPPLIERS</a></li>
                <li><a href="{{ url('/reports') }}">REPORTS</a></li>
            </ul>
        </nav>
        <div class="bottom-nav">
            <ul>
                <li><a href="{{ url('/settings') }}" style="display:flex;align-items:center;gap:12px;color:inherit;text-decoration:none;width:100%;">
                    <img src="{{ asset('images/settings.jpg') }}" alt="Settings" class="nav-icon"> Settings
                </a></li>
                <li class="logout">
                    <form method="POST" action="{{ url('/logout') }}" style="width:100%;margin:0;padding:0;">
                        @csrf
                        <button type="submit" style="display:flex;align-items:center;gap:12px;color:inherit;text-decoration:none;width:100%;background:none;border:none;cursor:pointer;padding:0;font:inherit;color:inherit;">
                            <img src="{{ asset('images/logout.jpg') }}" alt="Log Out" class="nav-icon"> Log out
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
                <button class="btn-add-data" onclick="openPfimsImport()">Import Expenses</button>
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
                <span class="tab" data-period="all" onclick="setActiveTab(this,'all')">All</span>
                <span class="tab" data-period="daily" onclick="setActiveTab(this,'daily')">Daily</span>
                <span class="tab" data-period="weekly" onclick="setActiveTab(this,'weekly')">Weekly</span>
                <span class="tab active" data-period="monthly" onclick="setActiveTab(this,'monthly')">Monthly</span>
                <span class="tab" data-period="yearly" onclick="setActiveTab(this,'yearly')">Yearly</span>
            </div>
            <div class="filter-row">
                <select id="projectFilter" class="project-filter" onchange="filterByProject()"><option value="all">All Projects</option></select>
                <select id="expenseCategoryFilter" onchange="applyFilters()"><option value="all">All Categories</option></select>
                <select id="expenseComponentFilter" onchange="applyFilters()"><option value="all">All Components</option><option value="material">Material</option><option value="labor">Labor</option><option value="equipment">Equipment</option><option value="other">Other</option></select>
                <input type="search" id="projectSearch" class="project-filter" maxlength="150" placeholder="Search project, category, description..." oninput="applyFilters()">
                <button type="button" class="btn-clear-search" onclick="clearSearch()">✕ Clear Filters</button>
            </div>
            <div class="module-insights">
                <section class="module-insight-card" aria-labelledby="expenseCategoryChartTitle">
                    <h3 id="expenseCategoryChartTitle">Expenses by Category</h3>
                    <p class="insight-caption">Amounts are calculated only from expenses matching the filters above.</p>
                    <div id="expenseCategoryChart" class="insight-chart" role="img" aria-label="Filtered expenses by category"></div>
                </section>
            </div>
            <div class="table-wrapper expense-table-wrapper">
                <table id="expenseTable">
                    <thead><tr><th>Project</th><th>Expense Description</th><th>Category</th><th>Component</th><th>Amount</th><th>Date</th><th>Remarks</th><th>Actions</th></tr></thead>
                    <tbody id="expenseTableBody"></tbody>
                </table>
            </div>
            <div class="pagination-wrapper" id="expensePagination">
                <div class="rows-info">Rows per page
                    <select id="financeRowsPerPage" aria-label="Finance expense rows per page" onchange="changeFinancePageSize()">
                        <option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option>
                    </select>
                    <span id="rowsInfoText">Showing 0 of 0 expenses</span>
                </div>
                <div class="pagination-links" id="financePaginationLinks"></div>
            </div>
        </div>

        <!-- ─── TAB 2: BUDGETS ─── -->
        <div id="tabBudgets" class="report-section">
            <div class="stats-row-budget budget-stats visible">
                <div class="stat-mini"><div class="stat-label">Total Budget</div><div class="stat-value blue" id="budgetTotalValue">₱0.00</div></div>
                <div class="stat-mini"><div class="stat-label">Actual Spend</div><div class="stat-value" id="budgetSpentValue">₱0.00</div></div>
                <div class="stat-mini"><div class="stat-label">Remaining</div><div class="stat-value green" id="budgetRemainingValue">₱0.00</div></div>
            </div>
            <div class="filter-row">
                <select id="budgetProjectFilter" onchange="filterBudgetTable()"><option value="all">All Projects</option></select>
                <select id="budgetStatusFilter" onchange="filterBudgetTable()"><option value="all">All Statuses</option><option value="On Track">On Track</option><option value="Near Limit">Near Limit</option><option value="Over Budget">Over Budget</option><option value="No Budget">No Budget</option></select>
                <input type="search" id="budgetSearch" maxlength="150" placeholder="Search project name..." oninput="filterBudgetTable()">
                <button type="button" class="btn-clear-search" onclick="clearBudgetSearch()">✕ Clear Filters</button>
            </div>
            <div class="module-insights">
                <section class="module-insight-card" aria-labelledby="budgetStatusChartTitle">
                    <h3 id="budgetStatusChartTitle">Projects by Budget Status</h3>
                    <p class="insight-caption">Counts are calculated from the filtered budget rows.</p>
                    <div id="budgetStatusChart" class="insight-chart" role="img" aria-label="Filtered projects by budget status"></div>
                </section>
            </div>
            <div class="budget-table-wrapper visible">
                <table id="budgetTable">
                    <thead><tr><th>Project Name</th><th>Budget Amount</th><th>Actual Spend</th><th>Remaining</th><th>Status</th></tr></thead>
                    <tbody id="budgetTableBody"><tr><td colspan="5" style="text-align:center;padding:20px;">Loading budget data...</td></tr></tbody>
                </table>
            </div>
            <div class="pagination-wrapper" id="budgetPagination">
                <div class="rows-info">Rows per page
                    <select id="budgetRowsPerPage" aria-label="Finance budget rows per page" onchange="changeBudgetPageSize()">
                        <option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option>
                    </select>
                    <span id="budgetRowsInfo">Showing 0 of 0 projects</span>
                </div>
                <div class="pagination-links" id="budgetPaginationLinks"></div>
            </div>
        </div>

        <!-- ─── TAB 3: EXPOVRALL ─── -->
        <div id="tabExpovrall" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
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
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
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
            </div>
            <div class="report-table-wrapper">
                <table id="adminexpTable">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Salaries & Wages</th>
                            <th>Permit, Taxes</th>
                            <th>Transport</th>
                            <th>Utilities</th>
                            <th>Delivery</th>
                            <th>Rent</th>
                            <th>Stationery</th>
                            <th>Depreciation</th>
                            <th>Repair & Maint</th>
                            <th>Misc</th>
                            <th>Penalty</th>
                            <th>SSS/PhilHealth</th>
                            <th>Others</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="adminexpBody"><tr><td colspan="15" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- ─── TAB 6: DIRECT EXP ─── -->
        <div id="tabDirectexp" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
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
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
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
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Report Type:
                    <select id="profitType" onchange="loadProfit()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                        <option value="direct">Direct Expenses</option>
                        <option value="overall">Overall Expenses</option>
                    </select>
                </label>
                <button onclick="loadProfit()" style="padding:6px 16px;background:#1a2b3c;color:#fff;border:none;border-radius:6px;cursor:pointer;">Refresh</button>
                <button onclick="openAddContractModal()" class="btn-add-data gold">+ Add Contract</button>
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
                <button onclick="openAddReceivableModal()" class="btn-add-data gold">+ Add Entry</button>
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
                <button onclick="openAddCashModal()" class="btn-add-data gold">+ Add Cash Position</button>
            </div>
            <div class="report-table-wrapper">
                <table id="cashTable">
                    <thead><tr><th>Account</th><th>Balance</th></tr></thead>
                    <tbody id="cashBody"><tr><td colspan="3" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
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
                <button onclick="openAddBackhoeRentalModal()" class="btn-add-data gold">+ Add Rental Income</button>
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
                <button onclick="openAddBondModal()" class="btn-add-data gold">+ Add Bond</button>
            </div>
            <div class="report-table-wrapper">
                <table id="bondTable">
                    <thead><tr><th>Date</th><th>Project</th><th>Provider</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody id="bondBody"><tr><td colspan="6" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- ─── TAB 14: SUMMARY ─── -->
        <div id="tabSummary" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
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

    <div id="inventoryExpenseModal" class="modal-overlay">
        <div class="modal-container" style="max-width:420px;">
            <div class="modal-header"><h2>Add Stock-In Expense</h2><button class="modal-close" onclick="closeInventoryExpenseModal()">×</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Amount <span class="required">*</span></label><input type="number" id="inventoryExpenseAmount" min="0.01" step="0.01" placeholder="0.00"></div>
            </div>
            <div class="modal-footer"><button class="btn-cancel" onclick="closeInventoryExpenseModal()">Cancel</button><button class="btn-save" id="inventoryExpenseSaveBtn" onclick="saveInventoryExpense()">Add Expense</button></div>
        </div>
    </div>

    <!-- ─── ADD EXPENSE MODAL (Unified - Handles ALL Expense Types) ─── -->
    <div id="addExpenseModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header"><h2 id="addExpenseModalTitle">Add Expense</h2><button class="modal-close" onclick="closeAddExpenseModal()">×</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Project</label><select id="expenseProject"><option value="">Office/Admin (no project)</option></select></div>
                <div class="form-group"><label>Expense Description <span class="required">*</span></label><input type="text" placeholder="e.g. Office Rent, Salary, Materials" id="expenseDesc"></div>
                <div class="form-group"><label>Category <span class="required">*</span></label>
                    <select id="expenseCategory" onchange="toggleExpenseAmountFields()">
                        <option value="">Select Category...</option>
                    </select>
                </div>
                <div class="form-group"><label>Project Cost Component</label>
                    <select id="expenseCostComponent">
                        <option value="">No project component</option>
                        <option value="material">Material</option>
                        <option value="labor">Labor</option>
                        <option value="equipment">Equipment</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <!-- Dynamic Amount Fields -->
                <div id="dynamicAmountFields" class="dynamic-amount-fields" style="display:none;">
                    <div class="form-group">
                        <label>Labor Amount</label>
                        <input type="number" step="0.01" placeholder="0.00" id="expenseLaborAmount">
                    </div>
                    <div class="form-group">
                        <label>Material Amount</label>
                        <input type="number" step="0.01" placeholder="0.00" id="expenseMaterialAmount">
                    </div>
                    <div class="form-group">
                        <label>Equipment Amount</label>
                        <input type="number" step="0.01" placeholder="0.00" id="expenseEquipmentAmount">
                    </div>
                    <div class="form-group">
                        <label>Other Amount</label>
                        <input type="number" step="0.01" placeholder="0.00" id="expenseOtherAmount">
                    </div>
                </div>

                <!-- Single Amount Field (for all categories) -->
                <div id="singleAmountField" class="form-group">
                    <label>Amount <span class="required">*</span></label>
                    <input type="number" step="0.01" placeholder="0.00" id="expenseAmount">
                </div>

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

    <!-- ─── ADD CONTRACT MODAL ─── -->
    <div id="addContractModal" class="modal-overlay">
        <div class="modal-container" style="width:600px;max-width:95%;">
            <div class="modal-header">
                <h2 id="contractModalTitle">Add/Edit Contract</h2>
                <button class="modal-close" onclick="closeAddContractModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Project <span class="required">*</span></label>
                    <select id="contractProject" onchange="updateContractBudgetDisplay()"><option value="">Select Project...</option></select>
                    <span id="contractProjectDisplay" style="display:none;font-weight:600;font-size:1rem;color:#1a2b3c;padding:10px 0;">Project Name</span>
                </div>
                <div class="form-group" style="background:#f8f6f3;padding:12px 16px;border-radius:8px;">
                    <label style="font-weight:600;color:#1a2b3c;">Contract Price</label>
                    <span id="contractBudgetDisplay" style="font-size:1.2rem;font-weight:700;color:#c9a96e;">₱0.00</span>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Additional Works (Contract)</label>
                        <input type="number" step="0.01" placeholder="0.00" id="contractAddlWorks">
                    </div>
                    <div class="form-group">
                        <label>Additional Works (Payment)</label>
                        <input type="number" step="0.01" placeholder="0.00" id="contractAddlPayment">
                        <small style="color:#888;font-size:0.7rem;">Additional payment received</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Payment Received</label>
                        <input type="number" step="0.01" placeholder="0.00" id="contractPayment">
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <input type="text" placeholder="Additional notes..." id="contractRemarks">
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="justify-content:flex-end;gap:12px;">
                <button class="btn-cancel" onclick="closeAddContractModal()">Cancel</button>
                <button class="btn-delete" id="contractDeleteBtn" onclick="deleteContract()" style="display:none;">Delete</button>
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
                <div class="detail-item"><label>Project Cost Component</label><span id="detailCostComponentDisplay" class="detail-value">—</span><select id="detailCostComponentEdit" class="detail-edit" style="display:none;"><option value="">No project component</option><option value="material">Material</option><option value="labor">Labor</option><option value="equipment">Equipment</option><option value="other">Other</option></select></div>
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

    <!-- ─── RECEIVABLE DETAIL MODAL ─── -->
    <div id="receivableDetailModal" class="modal-overlay modal-update">
        <div class="modal-container" style="width:750px;max-width:95%;">
            <div class="modal-header"><div><h2 id="receivableModalTitle">AR/AP Entry Details</h2></div><button class="modal-close" onclick="closeReceivableModal()">×</button></div>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Entry Type</label>
                    <span id="receivableDetailType" class="detail-value">—</span>
                    <select id="receivableDetailTypeEdit" class="detail-edit" style="display:none;">
                        <option value="accounts_receivable">Accounts Receivable</option>
                        <option value="accounts_payable">Accounts Payable</option>
                        <option value="cash_advance_site">Cash Advance (Site)</option>
                        <option value="advance_employee">Advances to Employees</option>
                    </select>
                </div>
                <div class="detail-item">
                    <label>Counterparty</label>
                    <span id="receivableDetailCounterparty" class="detail-value">—</span>
                    <span id="receivableDetailCounterpartyDisplay" class="detail-value" style="font-weight:600;color:#1a2b3c;display:none;">—</span>
                    <input type="text" id="receivableDetailCounterpartyEdit" class="detail-edit" style="display:none;">
                </div>
                <div class="detail-item">
                    <label>Project</label>
                    <span id="receivableDetailProject" class="detail-value">—</span>
                    <span id="receivableDetailProjectDisplay" class="detail-value" style="font-weight:600;color:#1a2b3c;display:none;">—</span>
                    <select id="receivableDetailProjectEdit" class="detail-edit" style="display:none;"></select>
                </div>
                <div class="detail-item">
                    <label>Date</label>
                    <span id="receivableDetailDate" class="detail-value">—</span>
                    <input type="date" id="receivableDetailDateEdit" class="detail-edit" style="display:none;">
                </div>
                <div class="detail-item">
                    <label>30 Days</label>
                    <span id="receivableDetail30d" class="detail-value">—</span>
                    <input type="number" step="0.01" id="receivableDetail30dEdit" class="detail-edit" style="display:none;">
                </div>
                <div class="detail-item">
                    <label>31-60 Days</label>
                    <span id="receivableDetail60d" class="detail-value">—</span>
                    <input type="number" step="0.01" id="receivableDetail60dEdit" class="detail-edit" style="display:none;">
                </div>
                <div class="detail-item">
                    <label>61-90 Days</label>
                    <span id="receivableDetail90d" class="detail-value">—</span>
                    <input type="number" step="0.01" id="receivableDetail90dEdit" class="detail-edit" style="display:none;">
                </div>
                <div class="detail-item">
                    <label>91-120 Days</label>
                    <span id="receivableDetail120d" class="detail-value">—</span>
                    <input type="number" step="0.01" id="receivableDetail120dEdit" class="detail-edit" style="display:none;">
                </div>
                <div class="detail-item">
                    <label>Status</label>
                    <span id="receivableDetailStatus" class="detail-value">—</span>
                    <select id="receivableDetailStatusEdit" class="detail-edit" style="display:none;">
                        <option value="outstanding">Outstanding</option>
                        <option value="settled">Settled</option>
                    </select>
                </div>
                <div class="detail-item">
                    <label>Remarks</label>
                    <span id="receivableDetailRemarks" class="detail-value">—</span>
                    <input type="text" id="receivableDetailRemarksEdit" class="detail-edit" style="display:none;">
                </div>
            </div>
            <div class="modal-footer" style="justify-content:flex-end;gap:12px;">
                <button class="btn-cancel" onclick="closeReceivableModal()">Close</button>
                <button class="btn-delete" id="receivableDetailDeleteBtn" onclick="deleteReceivable()">Delete</button>
                <button class="btn-edit-project" id="receivableDetailEditBtn" onclick="toggleReceivableEdit()">Edit</button>
                <button class="btn-save" id="receivableDetailSaveBtn" style="display:none;" onclick="saveReceivableDetail()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- ─── BOND DETAIL MODAL ─── -->
    <div id="bondDetailModal" class="modal-overlay modal-update">
        <div class="modal-container" style="width:600px;max-width:95%;">
            <div class="modal-header"><div><h2 id="bondModalTitle">Bond Details</h2></div><button class="modal-close" onclick="closeBondModal()">×</button></div>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Project</label>
                    <span id="bondDetailProject" class="detail-value">—</span>
                    <span id="bondDetailProjectDisplay" class="detail-value" style="font-weight:600;color:#1a2b3c;display:none;">—</span>
                    <select id="bondDetailProjectEdit" class="detail-edit" style="display:none;"></select>
                </div>
                <div class="detail-item">
                    <label>Date</label>
                    <span id="bondDetailDate" class="detail-value">—</span>
                    <input type="date" id="bondDetailDateEdit" class="detail-edit" style="display:none;">
                </div>
                <div class="detail-item">
                    <label>Amount</label>
                    <span id="bondDetailAmount" class="detail-value">—</span>
                    <input type="number" step="0.01" id="bondDetailAmountEdit" class="detail-edit" style="display:none;">
                </div>
                <div class="detail-item">
                    <label>Provider</label>
                    <span id="bondDetailProvider" class="detail-value">—</span>
                    <input type="text" id="bondDetailProviderEdit" class="detail-edit" style="display:none;">
                </div>
                <div class="detail-item">
                    <label>Status</label>
                    <span id="bondDetailStatus" class="detail-value">—</span>
                    <select id="bondDetailStatusEdit" class="detail-edit" style="display:none;">
                        <option value="active">Active</option>
                        <option value="released">Released</option>
                        <option value="forfeited">Forfeited</option>
                    </select>
                </div>
                <div class="detail-item">
                    <label>Remarks</label>
                    <span id="bondDetailRemarks" class="detail-value">—</span>
                    <input type="text" id="bondDetailRemarksEdit" class="detail-edit" style="display:none;">
                </div>
            </div>
            <div class="modal-footer" style="justify-content:flex-end;gap:12px;">
                <button class="btn-cancel" onclick="closeBondModal()">Close</button>
                <button class="btn-delete" id="bondDetailDeleteBtn" onclick="deleteBond()">Delete</button>
                <button class="btn-edit-project" id="bondDetailEditBtn" onclick="toggleBondEdit()">Edit</button>
                <button class="btn-save" id="bondDetailSaveBtn" style="display:none;" onclick="saveBondDetail()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- ─── CASH ASSET DETAIL MODAL ─── -->
    <div id="cashDetailModal" class="modal-overlay modal-update">
        <div class="modal-container" style="width:500px;max-width:95%;">
            <div class="modal-header"><div><h2 id="cashModalTitle">Cash Position Details</h2></div><button class="modal-close" onclick="closeCashModal()">×</button></div>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Account</label>
                    <span id="cashDetailAccount" class="detail-value">—</span>
                    <span id="cashDetailAccountDisplay" class="detail-value" style="font-weight:600;color:#1a2b3c;display:none;">—</span>
                    <select id="cashDetailAccountEdit" class="detail-edit" style="display:none;"></select>
                </div>
                <div class="detail-item">
                    <label>Month</label>
                    <span id="cashDetailPeriod" class="detail-value">—</span>
                    <input type="month" id="cashDetailPeriodEdit" class="detail-edit" style="display:none;">
                </div>
                <div class="detail-item">
                    <label>Balance</label>
                    <span id="cashDetailBalance" class="detail-value">—</span>
                    <input type="number" step="0.01" id="cashDetailBalanceEdit" class="detail-edit" style="display:none;">
                </div>
            </div>
            <div class="modal-footer" style="justify-content:flex-end;gap:12px;">
                <button class="btn-cancel" onclick="closeCashModal()">Close</button>
                <button class="btn-delete" id="cashDetailDeleteBtn" onclick="deleteCashPosition()">Delete</button>
                <button class="btn-edit-project" id="cashDetailEditBtn" onclick="toggleCashEdit()">Edit</button>
                <button class="btn-save" id="cashDetailSaveBtn" style="display:none;" onclick="saveCashDetail()">Save Changes</button>
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
        var pendingInventoryTransactionId = null;
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

        var API_BASE = '/api';
        var assets = [];

        // ─── ADMIN CATEGORY CODES ──────────────────────────────────────
        var ADMIN_CATEGORY_CODES = ['RENT', 'STATIONERY', 'DEPRECIATION', 'REPAIR_MAINT', 'MISC', 'PENALTY', 'SSS_PHILHEALTH'];
        var PROJECT_COST_COMPONENT_LABELS = {
            material: 'Material',
            labor: 'Labor',
            equipment: 'Equipment',
            other: 'Other'
        };

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

        function isAdminCategory(categoryCode) {
            return ADMIN_CATEGORY_CODES.indexOf(categoryCode) !== -1;
        }

        function formatCostComponent(component) {
            return PROJECT_COST_COMPONENT_LABELS[component] || '—';
        }

        // ─── DYNAMIC EXPENSE FIELDS ──────────────────────────────────
        function toggleExpenseAmountFields() {
            var categorySelect = document.getElementById('expenseCategory');
            var selectedOption = categorySelect.options[categorySelect.selectedIndex];
            var categoryCode = selectedOption ? selectedOption.getAttribute('data-code') || '' : '';
            var categoryName = (selectedOption ? selectedOption.text : '').toLowerCase();
            var dynamicFields = document.getElementById('dynamicAmountFields');
            var singleField = document.getElementById('singleAmountField');

            // Check if it's a category with sub-amounts (labor, material, equipment, other)
            var isDynamicCategory = ['labor', 'material', 'equipment', 'other'].indexOf(categoryName) !== -1;

            if (isDynamicCategory) {
                dynamicFields.style.display = 'grid';
                singleField.style.display = 'none';
                // Show only the relevant field for the selected category
                var fieldMap = {
                    'labor': 'expenseLaborAmount',
                    'material': 'expenseMaterialAmount',
                    'equipment': 'expenseEquipmentAmount',
                    'other': 'expenseOtherAmount'
                };
                var fieldId = fieldMap[categoryName];
                if (fieldId) {
                    document.querySelectorAll('#dynamicAmountFields input').forEach(function(input) {
                        var parent = input.closest('.form-group');
                        if (parent) {
                            parent.style.display = 'none';
                        }
                    });
                    var targetField = document.getElementById(fieldId);
                    if (targetField) {
                        var parentGroup = targetField.closest('.form-group');
                        if (parentGroup) {
                            parentGroup.style.display = 'block';
                        }
                    }
                }
            } else {
                dynamicFields.style.display = 'none';
                singleField.style.display = 'block';
            }

            // Update modal title based on category
            var title = document.getElementById('addExpenseModalTitle');
            if (title) {
                if (isAdminCategory(categoryCode)) {
                    title.textContent = 'Add Admin Expense';
                } else if (isDynamicCategory) {
                    title.textContent = 'Add ' + categoryName.charAt(0).toUpperCase() + categoryName.slice(1) + ' Expense';
                } else {
                    title.textContent = 'Add Expense';
                }
            }
        }

        // ─── EXPENSE DETAIL FILE SELECTION ────────────────────────────
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

        // ─── BUDGET DETAIL FILE SELECTION ─────────────────────────────
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
            var isFormData = options && options.body instanceof FormData;
            var headers = {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            };

            if (!isFormData && options && options.headers) {
                headers = { ...headers, ...options.headers };
            }

            return fetch(API_BASE + endpoint, {
                ...options,
                headers: isFormData ? headers : { ...headers, 'Content-Type': 'application/json' },
                credentials: 'same-origin'
            }).then(function(response) {
                if (!response.ok) {
                    return response.text().then(function(text) {
                        var data = null;
                        try {
                            data = JSON.parse(text);
                        } catch (e) {}
                        var validationMessage = data && data.errors ? Object.values(data.errors).flat().join(', ') : '';
                        throw new Error((data && (data.message || data.error)) || validationMessage || 'Server error: ' + text.substring(0, 100));
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
            document.getElementById('projectFilter').value = 'all';
            document.getElementById('expenseCategoryFilter').value = 'all';
            document.getElementById('expenseComponentFilter').value = 'all';
            currentSearchTerm = '';
            currentProjectFilter = 'all';
            applyFilters();
        }

        function applyFilters() {
            if (currentReportTab !== 'expenses') return;

            var searchTerm = document.getElementById('projectSearch').value.toLowerCase().trim();
            var categoryFilter = document.getElementById('expenseCategoryFilter').value;
            var componentFilter = document.getElementById('expenseComponentFilter').value;
            currentSearchTerm = searchTerm;

            var projectFiltered = currentProjectFilter === 'all'
                ? financeExpenses
                : financeExpenses.filter(function(expense) { return expense.project_name === currentProjectFilter; });

            var categoryFiltered = categoryFilter === 'all'
                ? projectFiltered
                : projectFiltered.filter(function(expense) { return String(expense.fin_category_id || expense.expense_category_id || '') === String(categoryFilter); });

            var componentFiltered = componentFilter === 'all'
                ? categoryFiltered
                : categoryFiltered.filter(function(expense) { return expense.project_cost_component === componentFilter; });

            var searchFiltered = componentFiltered;
            if (searchTerm) {
                searchFiltered = componentFiltered.filter(function(expense) {
                    var projectName = (expense.project_name || '').toLowerCase();
                    var description = (expense.expense_description || '').toLowerCase();
                    var category = (expense.category_name || '').toLowerCase();
                    var component = formatCostComponent(expense.project_cost_component).toLowerCase();
                    var remarks = (expense.remarks || '').toLowerCase();
                    return projectName.includes(searchTerm) || description.includes(searchTerm) || category.includes(searchTerm) || component.includes(searchTerm) || remarks.includes(searchTerm);
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
            return fetch(API_BASE + '/projects', {
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
                populateBondProjectFilter();
                if (currentReportTab === 'expenses') updateFinanceTotals();
            })
            .catch(function(error) {
                console.error('fetchProjects error:', error);
                showError(error.message);
            });
        }

        function fetchExpenseCategories() {
            return apiFetch('/finance-categories')
                .then(function(data) {
                    console.log('Categories loaded:', data);
                financeCategories = data || [];
                populateCategoryDropdown();
                populateCategoryFilter();
            })
                .catch(function(error) { 
                    console.error('Error fetching categories:', error);
                    showError(error.message); 
                });
        }

        function fetchExpenses() {
            return apiFetch('/finance-expenses')
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
            return fetch(API_BASE + '/company-assets', {
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
            return fetch(API_BASE + '/budgets', {
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
                return fetch(API_BASE + '/projects', {
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
                            budget_id: b.budget_id,
                            proof_file_path: b.proof_file_path || '',
                            proof_file_name: b.proof_file_name || '',
                            status: budgetAmount === 0 ? 'No Budget' : (actualSpend > budgetAmount ? 'Over Budget' : (actualSpend > budgetAmount * 0.9 ? 'Near Limit' : 'On Track'))
                        };
                    });

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

                    filterBudgetTable();
                });
            })
            .catch(function(error) {
                showError('Error loading budget data: ' + error.message);
                document.getElementById('budgetTableBody').innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:#d32f2f;">Error loading budget data</td></tr>';
            });
        }

        // ─── POPULATE DROPDOWNS ───────────────────────────────────────
        function populateProjectDropdowns() {
            var selects = ['expenseProject', 'budgetProject', 'detailProjectEdit', 'bondProject', 'receivableProject', 'backhoeExpenseProject', 'backhoeRentalProject', 'contractProject', 'receivableDetailProjectEdit'];
            selects.forEach(function(id) {
                var select = document.getElementById(id);
                if (!select) return;
                select.innerHTML = '<option value="">Select Project...</option>';
                if (id === 'expenseProject' || id === 'detailProjectEdit') {
                    select.innerHTML = '<option value="">Office/Admin (no project)</option>';
                }
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
                var currentValue = select.value;
                select.innerHTML = '<option value="">Select Category...</option>';
                financeCategories.forEach(function(category) {
                    var option = document.createElement('option');
                    option.value = category.fin_category_id || category.expense_category_id;
                    option.setAttribute('data-code', category.category_code || '');
                    option.setAttribute('data-classification', category.classification || '');
                    // Add classification indicator
                    option.textContent = category.category_name || category.category_code || category;
                    select.appendChild(option);
                });
                if (currentValue) {
                    var options = select.querySelectorAll('option');
                    for (var i = 0; i < options.length; i++) {
                        if (options[i].value == currentValue) {
                            select.value = currentValue;
                            break;
                        }
                    }
                }
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

        function populateCategoryFilter() {
            var filter = document.getElementById('expenseCategoryFilter');
            if (!filter) return;
            var currentValue = filter.value || 'all';
            filter.innerHTML = '<option value="all">All Categories</option>';
            financeCategories.forEach(function(category) {
                var option = document.createElement('option');
                option.value = category.fin_category_id || category.expense_category_id;
                option.textContent = category.category_name || category.category_code || category;
                filter.appendChild(option);
            });
            filter.value = currentValue;
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
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:20px;">No expenses found.</td></tr>';
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
                row.setAttribute('data-cost-component', expense.project_cost_component || '');
                row.setAttribute('data-amount', expense.amount || '0');
                row.setAttribute('data-date', expense.expense_date || '');
                row.setAttribute('data-remarks', expense.remarks || '');
                row.setAttribute('data-proof-file-path', expense.proof_file_path || '');
                row.setAttribute('data-proof-file-name', expense.proof_file_name || '');
                row.style.cursor = expense.is_pending_inventory ? 'default' : 'pointer';
                if (!expense.is_pending_inventory) row.onclick = function() { openExpenseModal(this); };

                var categoryName = expense.category_name || '';
                var categoryClass = categoryName.toLowerCase().replace(/[^a-z]/g, '-');
                // Check if admin category
                var isAdmin = false;
                var catCode = '';
                var categoryObj = financeCategories.find(function(c) {
                    return (c.fin_category_id == expense.fin_category_id || c.expense_category_id == expense.expense_category_id);
                });
                if (categoryObj) {
                    catCode = categoryObj.category_code || '';
                    isAdmin = isAdminCategory(catCode);
                }
                if (isAdmin) categoryClass = 'admin';
                else if (['labor', 'material', 'equipment', 'other'].indexOf(categoryClass) === -1) categoryClass = 'other';

                row.innerHTML = '<td><strong>' + (expense.project_name || '') + '</strong></td>' +
                '<td>' + (expense.expense_description || '') + '</td>' +
                '<td><span class="category-badge ' + categoryClass + '">' + categoryName + '</span></td>' +
                '<td>' + formatCostComponent(expense.project_cost_component) + '</td>' +
                '<td>' + (expense.is_pending_inventory ? '—' : formatCurrency(expense.amount || 0)) + '</td>' +
                '<td>' + (expense.expense_date || '') + '</td>' +
                '<td>' + (expense.remarks || '—') + '</td>' +
                '<td>' + (expense.is_pending_inventory ? '<button class="btn-add-expense" onclick="event.stopPropagation(); openInventoryExpenseModal(' + expense.inventory_transaction_id + ')">Add Expense</button>' : '—') + '</td>';
                tbody.appendChild(row);
            });

            renderFinancePagination();
            updateRowsInfo(financeFilteredData.length);
        }

        function openInventoryExpenseModal(transactionId) {
            pendingInventoryTransactionId = transactionId;
            document.getElementById('inventoryExpenseAmount').value = '';
            document.getElementById('inventoryExpenseModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeInventoryExpenseModal() {
            document.getElementById('inventoryExpenseModal').classList.remove('active');
            document.body.style.overflow = '';
            pendingInventoryTransactionId = null;
        }

        function saveInventoryExpense() {
            var transactionId = pendingInventoryTransactionId;
            var amount = parseFloat(document.getElementById('inventoryExpenseAmount').value);
            if (!transactionId || !amount || amount < 0.01) {
                showError('Please enter a valid amount.');
                return;
            }

            var saveButton = document.getElementById('inventoryExpenseSaveBtn');
            saveButton.disabled = true;
            saveButton.textContent = 'Saving...';

            apiFetch('/finance-expenses/from-inventory/' + transactionId, {
                method: 'POST',
                body: JSON.stringify({ amount: amount })
            }).then(function() {
                return fetchExpenses();
            }).then(function() {
                applyFilters();
                closeInventoryExpenseModal();
                showSuccess('Stock-in expense added.');
            }).catch(function(error) {
                showError(error.message || 'Unable to add stock-in expense.');
            }).finally(function() {
                saveButton.disabled = false;
                saveButton.textContent = 'Add Expense';
            });
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
                row.style.cursor = 'pointer';
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

            var budget = budgetData.find(function(b) {
                return String(b.project_id) === String(projectId);
            });

            var budgetDetailFormData = new FormData();
            budgetDetailFormData.append('_method', 'PUT');
            budgetDetailFormData.append('project_id', projectId);
            budgetDetailFormData.append('budget_amount', budgetAmount);
            if (selectedBudgetDetailFile) {
                budgetDetailFormData.append('proof_file', selectedBudgetDetailFile);
            } else if (budgetFileMarkedForRemoval) {
                budgetDetailFormData.append('remove_proof_file', '1');
            }

            var url = budget && budget.budget_id ? '/budgets/' + budget.budget_id : '/budgets';

            fetch(API_BASE + url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: budgetDetailFormData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) {
                        throw new Error(data.message || 'Unable to update budget.');
                    });
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
                var budget = budgetData.find(function(b) {
                    return String(b.project_id) === String(projectId);
                });

                if (budget && budget.budget_id) {
                    apiFetch('/budgets/' + budget.budget_id, { method: 'DELETE' })
                        .then(function() {
                            budgetData = budgetData.filter(function(item) {
                                return String(item.project_id) !== String(projectId);
                            });
                            budgetFilteredData = budgetFilteredData.filter(function(item) {
                                return String(item.project_id) !== String(projectId);
                            });
                            closeBudgetDeleteModal();
                            renderBudgetPage(1);
                            updateBudgetStats();
                            showSuccess('Budget deleted successfully!');
                            currentBudgetRow = null;
                        })
                        .catch(function(error) { showError(error.message); });
                } else {
                    budgetData = budgetData.filter(function(item) {
                        return String(item.project_id) !== String(projectId);
                    });
                    budgetFilteredData = budgetFilteredData.filter(function(item) {
                        return String(item.project_id) !== String(projectId);
                    });
                    closeBudgetDeleteModal();
                    renderBudgetPage(1);
                    updateBudgetStats();
                    showSuccess('Budget removed from view.');
                    currentBudgetRow = null;
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
            document.getElementById('detailCostComponentDisplay').textContent = formatCostComponent(row.dataset.costComponent);
            document.getElementById('detailAmountDisplay').textContent = formatCurrency(row.dataset.amount);
            document.getElementById('detailDateDisplay').textContent = row.dataset.date;
            document.getElementById('detailRemarksDisplay').textContent = row.dataset.remarks || '—';

            document.getElementById('detailProjectEdit').value = row.dataset.projectId || '';
            document.getElementById('detailDescEdit').value = row.dataset.desc;
            document.getElementById('detailCategoryEdit').value = row.dataset.categoryId || '';
            document.getElementById('detailCostComponentEdit').value = row.dataset.costComponent || '';
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
            var costComponent = document.getElementById('detailCostComponentEdit').value;
            var amount = parseFloat(document.getElementById('detailAmountEdit').value) || 0;
            var date = document.getElementById('detailDateEdit').value;
            var remarks = document.getElementById('detailRemarksEdit').value.trim();

            if (!desc || !categoryId || !amount || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            if (amount <= 0) { showError('Amount must be greater than 0.'); return; }

            var detailCategory = financeCategories.find(function(c) {
                return c.fin_category_id == categoryId || c.expense_category_id == categoryId;
            });
            var isDirectDetailCategory = detailCategory && String(detailCategory.classification || '').toLowerCase() === 'direct';
            if (isDirectDetailCategory && !projectId) {
                showError('Direct project expenses require a project.');
                return;
            }
            if ((isDirectDetailCategory || projectId) && !costComponent) {
                showError('Please select a project cost component.');
                return;
            }

            var expenseId = currentDetailRow.getAttribute('data-expense-id');

            var detailFormData = new FormData();
            detailFormData.append('_method', 'PUT');
            detailFormData.append('project_id', projectId);
            detailFormData.append('fin_category_id', categoryId);
            detailFormData.append('project_cost_component', costComponent);
            detailFormData.append('expense_description', desc);
            detailFormData.append('amount', amount);
            detailFormData.append('expense_date', date);
            if (remarks) detailFormData.append('remarks', remarks);
            if (selectedDetailFile) {
                detailFormData.append('proof_file', selectedDetailFile);
            } else if (expenseFileMarkedForRemoval) {
                detailFormData.append('remove_proof_file', '1');
            }

            fetch(API_BASE + '/finance-expenses/' + expenseId, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: detailFormData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) {
                        throw new Error(data.message || data.error || 'Unable to update expense.');
                    });
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
                apiFetch('/finance-expenses/' + expenseId, { method: 'DELETE' })
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

        // ─── ADD EXPENSE (Unified - Handles ALL Expense Types) ──────
        function openAddExpenseModal() {
            document.getElementById('addExpenseModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('expenseProject').value = '';
            document.getElementById('expenseDesc').value = '';
            document.getElementById('expenseCategory').value = '';
            document.getElementById('expenseCostComponent').value = '';
            document.getElementById('expenseAmount').value = '';
            document.getElementById('expenseLaborAmount').value = '';
            document.getElementById('expenseMaterialAmount').value = '';
            document.getElementById('expenseEquipmentAmount').value = '';
            document.getElementById('expenseOtherAmount').value = '';
            document.getElementById('expenseDate').value = '{{ date("Y-m-d") }}';
            document.getElementById('expenseRemarks').value = '';

            // Reset dynamic fields visibility
            document.getElementById('dynamicAmountFields').style.display = 'none';
            document.getElementById('singleAmountField').style.display = 'block';
            document.getElementById('addExpenseModalTitle').textContent = 'Add Expense';

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
            var costComponent = document.getElementById('expenseCostComponent').value;
            var date = document.getElementById('expenseDate').value;
            var remarks = document.getElementById('expenseRemarks').value.trim();

            if (!desc || !categoryId || !date) {
                showError('Please fill in all required fields.');
                return;
            }

            // Find the selected category
            var category = financeCategories.find(function(c) {
                return c.fin_category_id == categoryId || c.expense_category_id == categoryId;
            });

            if (!category) {
                showError('Invalid category selected.');
                return;
            }

            var categoryName = category.category_name ? category.category_name.toLowerCase() : '';
            var categoryCode = category.category_code || '';
            var isDirectCategory = String(category.classification || '').toLowerCase() === 'direct';

            if (isDirectCategory && !projectId) {
                showError('Direct project expenses require a project.');
                return;
            }
            if ((isDirectCategory || projectId) && !costComponent) {
                showError('Please select a project cost component.');
                return;
            }

            var expenseFormData = new FormData();
            expenseFormData.append('project_id', projectId);
            expenseFormData.append('fin_category_id', categoryId);
            expenseFormData.append('project_cost_component', costComponent);
            expenseFormData.append('expense_description', desc);
            expenseFormData.append('expense_date', date);
            if (remarks) expenseFormData.append('remarks', remarks);

            // Check if it's a dynamic category (labor, material, equipment, other)
            var isDynamicCategory = ['labor', 'material', 'equipment', 'other'].indexOf(categoryName) !== -1;

            var amount = 0;

            if (isDynamicCategory) {
                // Get the specific amount for the category
                var amountFieldMap = {
                    'labor': 'expenseLaborAmount',
                    'material': 'expenseMaterialAmount',
                    'equipment': 'expenseEquipmentAmount',
                    'other': 'expenseOtherAmount'
                };
                var fieldId = amountFieldMap[categoryName];
                amount = parseFloat(document.getElementById(fieldId).value) || 0;

                if (amount <= 0) {
                    showError('Please enter a valid amount for ' + categoryName + '.');
                    return;
                }
            } else {
                amount = parseFloat(document.getElementById('expenseAmount').value) || 0;
                if (amount <= 0) {
                    showError('Amount must be greater than 0.');
                    return;
                }
            }

            expenseFormData.append('amount', amount);

            var expenseProofFileInput = document.getElementById('expenseProofFile');
            if (expenseProofFileInput && expenseProofFileInput.files && expenseProofFileInput.files.length > 0) {
                expenseFormData.append('proof_file', expenseProofFileInput.files[0]);
            }

            // Check if it's an admin category
            var isAdmin = isAdminCategory(categoryCode);

            fetch(API_BASE + '/finance-expenses', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: expenseFormData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) {
                        throw new Error(data.message || data.error || 'Unable to save expense.');
                    });
                }
                return response.json();
            })
            .then(function() {
                closeAddExpenseModal();
                showSuccess((isAdmin ? 'Admin ' : '') + 'Expense added successfully!');
                fetchExpenses();
                fetchBudgetData();
                // Refresh all report views
                loadExpovrall();
                loadExpDirect();
                loadAdminExp();  // This ensures admin exp tab updates
                loadDirectExp();
                loadOverallExp();
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

            fetch(API_BASE + '/budgets', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: budgetFormData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) {
                        throw new Error(data.message || 'Unable to save budget.');
                    });
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

        // ─── ADD CONTRACT ─────────────────────────────────────────────
        function openAddContractModal(row) {
            document.getElementById('addContractModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Populate project dropdown
            populateContractProjectDropdown();
            
            var projectSelect = document.getElementById('contractProject');
            var projectDisplay = document.getElementById('contractProjectDisplay');
            var deleteBtn = document.getElementById('contractDeleteBtn');
            
            if (row) {
                // EDIT MODE - Project name is display-only
                document.getElementById('contractModalTitle').textContent = 'Edit Contract';
                var projectId = row.dataset.projectId;
                var projectName = row.dataset.projectName || row.querySelector('td:first-child')?.textContent || '';
                
                // Hide select, show display
                projectSelect.style.display = 'none';
                projectDisplay.style.display = 'block';
                projectDisplay.textContent = projectName;
                
                // Store project ID in a data attribute for saving
                document.getElementById('addContractModal').setAttribute('data-project-id', projectId);
                
                document.getElementById('contractAddlWorks').value = row.dataset.addlWorks || 0;
                document.getElementById('contractPayment').value = row.dataset.payment || 0;
                document.getElementById('contractAddlPayment').value = row.dataset.addlPayment || 0;
                document.getElementById('contractRemarks').value = row.dataset.remarks || '';
                document.getElementById('addContractModal').setAttribute('data-edit-id', row.dataset.contractId || '');
                
                // Show delete button in edit mode
                deleteBtn.style.display = 'inline-block';
                
                // Update budget display
                updateContractBudgetDisplayForProject(projectId);
            } else {
                // ADD MODE - Project is selectable
                document.getElementById('contractModalTitle').textContent = 'Add Contract';
                projectSelect.style.display = 'block';
                projectDisplay.style.display = 'none';
                projectSelect.value = '';
                document.getElementById('addContractModal').removeAttribute('data-project-id');
                document.getElementById('addContractModal').removeAttribute('data-edit-id');
                document.getElementById('contractAddlWorks').value = '0';
                document.getElementById('contractPayment').value = '0';
                document.getElementById('contractAddlPayment').value = '0';
                document.getElementById('contractRemarks').value = '';
                
                // Hide delete button in add mode
                deleteBtn.style.display = 'none';
                
                updateContractBudgetDisplay();
            }
        }

        function populateContractProjectDropdown() {
            var select = document.getElementById('contractProject');
            var currentValue = select.value;
            select.innerHTML = '<option value="">Select Project...</option>';
            financeProjects.forEach(function(project) {
                var option = document.createElement('option');
                option.value = project.project_id;
                option.textContent = project.project_name;
                // Add budget as data attribute
                option.setAttribute('data-budget', project.budget || 0);
                select.appendChild(option);
            });
            if (currentValue) {
                select.value = currentValue;
            }
        }

        function updateContractBudgetDisplay() {
            var select = document.getElementById('contractProject');
            var selectedOption = select.options[select.selectedIndex];
            var budget = selectedOption ? parseFloat(selectedOption.getAttribute('data-budget')) || 0 : 0;
            document.getElementById('contractBudgetDisplay').textContent = formatCurrency(budget);
        }

        function updateContractBudgetDisplayForProject(projectId) {
            var project = financeProjects.find(function(p) {
                return p.project_id == projectId;
            });
            var budget = project ? parseFloat(project.budget) || 0 : 0;
            document.getElementById('contractBudgetDisplay').textContent = formatCurrency(budget);
        }

        function closeAddContractModal() {
            document.getElementById('addContractModal').classList.remove('active');
            document.body.style.overflow = '';
            // Reset to add mode
            document.getElementById('contractProject').style.display = 'block';
            document.getElementById('contractProjectDisplay').style.display = 'none';
        }

        function saveContract() {
            var editId = document.getElementById('addContractModal').getAttribute('data-edit-id');
            var projectId;
            var contractPrice;
            
            if (editId) {
                // EDIT MODE - Get project ID from data attribute
                projectId = document.getElementById('addContractModal').getAttribute('data-project-id');
                if (!projectId) {
                    showError('Project information is missing.');
                    return;
                }
                // Get budget for this project
                var project = financeProjects.find(function(p) {
                    return p.project_id == projectId;
                });
                contractPrice = project ? parseFloat(project.budget) || 0 : 0;
            } else {
                // ADD MODE - Get from select
                var select = document.getElementById('contractProject');
                projectId = select.value;
                var selectedOption = select.options[select.selectedIndex];
                contractPrice = selectedOption ? parseFloat(selectedOption.getAttribute('data-budget')) || 0 : 0;
            }

            if (!projectId) {
                showError('Please select a project.');
                return;
            }

            if (contractPrice <= 0) {
                showError('This project has no budget set. Please add a budget first.');
                return;
            }

            var addlWorks = parseFloat(document.getElementById('contractAddlWorks').value) || 0;
            var payment = parseFloat(document.getElementById('contractPayment').value) || 0;
            var addlPayment = parseFloat(document.getElementById('contractAddlPayment').value) || 0;
            var remarks = document.getElementById('contractRemarks').value.trim();

            var payload = {
                project_id: parseInt(projectId),
                original_contract_price: contractPrice,
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

        function deleteContract() {
            var editId = document.getElementById('addContractModal').getAttribute('data-edit-id');
            if (!editId) {
                showError('No contract to delete.');
                return;
            }
            
            openDeleteModal('Are you sure you want to permanently delete this contract?', function() {
                apiFetch('/project-contracts/' + editId, { method: 'DELETE' })
                    .then(function() {
                        closeAddContractModal();
                        showSuccess('Contract deleted successfully!');
                        loadProfit();
                    })
                    .catch(function(error) { showError(error.message); });
            });
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

        // ─── RECEIVABLE DETAIL MODAL ──────────────────────────────────
        var currentReceivableRow = null;
        var isReceivableEditMode = false;

        function openReceivableModal(row) {
            currentReceivableRow = row;
            document.getElementById('receivableModalTitle').textContent = 'AR/AP Entry Details';
            
            // Display values (read-only mode)
            document.getElementById('receivableDetailType').textContent = formatEntryType(row.dataset.type || '');
            document.getElementById('receivableDetailCounterparty').textContent = row.dataset.counterparty || '';
            document.getElementById('receivableDetailProject').textContent = row.dataset.project || '—';
            document.getElementById('receivableDetailDate').textContent = row.dataset.date || '';
            document.getElementById('receivableDetail30d').textContent = formatCurrency(row.dataset.amount30d || 0);
            document.getElementById('receivableDetail60d').textContent = formatCurrency(row.dataset.amount60d || 0);
            document.getElementById('receivableDetail90d').textContent = formatCurrency(row.dataset.amount90d || 0);
            document.getElementById('receivableDetail120d').textContent = formatCurrency(row.dataset.amount120d || 0);
            document.getElementById('receivableDetailStatus').textContent = row.dataset.status || 'outstanding';
            document.getElementById('receivableDetailRemarks').textContent = row.dataset.remarks || '—';
            
            // Set edit values
            document.getElementById('receivableDetailTypeEdit').value = row.dataset.type || '';
            document.getElementById('receivableDetailCounterpartyEdit').value = row.dataset.counterparty || '';
            document.getElementById('receivableDetailProjectEdit').value = row.dataset.projectId || '';
            document.getElementById('receivableDetailDateEdit').value = row.dataset.date || '';
            document.getElementById('receivableDetail30dEdit').value = row.dataset.amount30d || 0;
            document.getElementById('receivableDetail60dEdit').value = row.dataset.amount60d || 0;
            document.getElementById('receivableDetail90dEdit').value = row.dataset.amount90d || 0;
            document.getElementById('receivableDetail120dEdit').value = row.dataset.amount120d || 0;
            document.getElementById('receivableDetailStatusEdit').value = row.dataset.status || 'outstanding';
            document.getElementById('receivableDetailRemarksEdit').value = row.dataset.remarks || '';

            // Store the project name for display in edit mode
            document.getElementById('receivableDetailProjectDisplay').textContent = row.dataset.project || '—';

            if (isReceivableEditMode) toggleReceivableEdit();
            isReceivableEditMode = false;
            document.getElementById('receivableDetailEditBtn').style.display = 'inline-block';
            document.getElementById('receivableDetailDeleteBtn').style.display = 'inline-block';
            document.getElementById('receivableDetailSaveBtn').style.display = 'none';
            
            // Reset display/edit visibility
            document.querySelectorAll('#receivableDetailModal .detail-edit').forEach(function(el) { 
                el.style.display = 'none'; 
            });
            document.querySelectorAll('#receivableDetailModal .detail-value').forEach(function(el) { 
                el.style.display = ''; 
            });
            // Hide the display-only counterparty and project spans initially
            document.getElementById('receivableDetailCounterpartyDisplay').style.display = 'none';
            document.getElementById('receivableDetailProjectDisplay').style.display = 'none';

            document.getElementById('receivableDetailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function formatEntryType(type) {
            var map = {
                'accounts_receivable': 'Accounts Receivable',
                'accounts_payable': 'Accounts Payable',
                'cash_advance_site': 'Cash Advance (Site)',
                'advance_employee': 'Advances to Employees'
            };
            return map[type] || type;
        }

        function closeReceivableModal() {
            document.getElementById('receivableDetailModal').classList.remove('active');
            document.body.style.overflow = '';
            if (isReceivableEditMode) toggleReceivableEdit();
        }

        function toggleReceivableEdit() {
            isReceivableEditMode = !isReceivableEditMode;
            var editBtn = document.getElementById('receivableDetailEditBtn');
            var deleteBtn = document.getElementById('receivableDetailDeleteBtn');
            var saveBtn = document.getElementById('receivableDetailSaveBtn');
            
            // Elements that should be editable (amounts, date, status, remarks)
            var editableFields = ['receivableDetailDateEdit', 'receivableDetail30dEdit', 'receivableDetail60dEdit', 
                                  'receivableDetail90dEdit', 'receivableDetail120dEdit', 'receivableDetailStatusEdit', 
                                  'receivableDetailRemarksEdit'];
            
            // Display only fields (entry type, counterparty, project)
            var displayFields = ['receivableDetailType', 'receivableDetailCounterparty', 'receivableDetailProject',
                                 'receivableDetailDate', 'receivableDetail30d', 'receivableDetail60d', 
                                 'receivableDetail90d', 'receivableDetail120d', 'receivableDetailStatus', 
                                 'receivableDetailRemarks'];

            if (isReceivableEditMode) {
                editBtn.style.display = 'none';
                deleteBtn.style.display = 'none';
                saveBtn.style.display = 'inline-block';
                
                // Hide display values
                displayFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.style.display = 'none';
                });
                
                // Show edit fields (only editable ones)
                editableFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.style.display = '';
                });
                
                // Show counterparty and project as read-only display spans
                document.getElementById('receivableDetailCounterpartyDisplay').style.display = '';
                document.getElementById('receivableDetailProjectDisplay').style.display = '';
                
                // Hide the counterparty edit input (keep it hidden since it's not editable)
                document.getElementById('receivableDetailCounterpartyEdit').style.display = 'none';
                // Hide the project edit select (keep it hidden since it's not editable)
                document.getElementById('receivableDetailProjectEdit').style.display = 'none';
                // Hide the entry type edit select (keep it hidden since it's not editable)
                document.getElementById('receivableDetailTypeEdit').style.display = 'none';
                
                // Update the read-only display values with current edit values
                document.getElementById('receivableDetailCounterpartyDisplay').textContent = 
                    document.getElementById('receivableDetailCounterpartyEdit').value;
                var projectSelect = document.getElementById('receivableDetailProjectEdit');
                var projectName = projectSelect.options[projectSelect.selectedIndex]?.text || '—';
                document.getElementById('receivableDetailProjectDisplay').textContent = projectName;
                
            } else {
                editBtn.style.display = 'inline-block';
                deleteBtn.style.display = 'inline-block';
                saveBtn.style.display = 'none';
                
                // Show display values
                displayFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.style.display = '';
                });
                
                // Hide edit fields
                editableFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.style.display = 'none';
                });
                
                // Hide the read-only display spans
                document.getElementById('receivableDetailCounterpartyDisplay').style.display = 'none';
                document.getElementById('receivableDetailProjectDisplay').style.display = 'none';
            }
        }

        function saveReceivableDetail() {
            if (!currentReceivableRow) return;
            var rpId = currentReceivableRow.getAttribute('data-rp-id');

            var payload = {
                entry_date: document.getElementById('receivableDetailDateEdit').value,
                amount_30d: parseFloat(document.getElementById('receivableDetail30dEdit').value) || 0,
                amount_31_60d: parseFloat(document.getElementById('receivableDetail60dEdit').value) || 0,
                amount_61_90d: parseFloat(document.getElementById('receivableDetail90dEdit').value) || 0,
                amount_91_120d: parseFloat(document.getElementById('receivableDetail120dEdit').value) || 0,
                status: document.getElementById('receivableDetailStatusEdit').value,
                remarks: document.getElementById('receivableDetailRemarksEdit').value.trim() || null
            };

            apiFetch('/receivables-payables/' + rpId, { method: 'PUT', body: JSON.stringify(payload) })
                .then(function() {
                    closeReceivableModal();
                    showSuccess('Entry updated successfully!');
                    loadReceivables();
                })
                .catch(function(error) { showError(error.message); });
        }

        function deleteReceivable() {
            if (!currentReceivableRow) return;
            var rpId = currentReceivableRow.getAttribute('data-rp-id');
            openDeleteModal('Are you sure you want to delete this entry?', function() {
                apiFetch('/receivables-payables/' + rpId, { method: 'DELETE' })
                    .then(function() {
                        closeReceivableModal();
                        showSuccess('Entry deleted successfully!');
                        loadReceivables();
                        currentReceivableRow = null;
                    })
                    .catch(function(error) { showError(error.message); });
            });
        }

        document.getElementById('receivableDetailModal').addEventListener('click', function(e) {
            if (e.target === this) closeReceivableModal();
        });

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

        function saveBondDetail() {
            if (!currentBondRow) return;
            var bondId = currentBondRow.getAttribute('data-bond-id');

            var payload = {
                bond_date: document.getElementById('bondDetailDateEdit').value,
                amount: parseFloat(document.getElementById('bondDetailAmountEdit').value) || 0,
                bond_provider: document.getElementById('bondDetailProviderEdit').value.trim() || null,
                status: document.getElementById('bondDetailStatusEdit').value,
                remarks: document.getElementById('bondDetailRemarksEdit').value.trim() || null
            };

            if (!payload.bond_date || payload.amount <= 0) {
                showError('Please fill in all required fields.');
                return;
            }

            apiFetch('/construction-bonds/' + bondId, { method: 'PUT', body: JSON.stringify(payload) })
                .then(function() {
                    closeBondModal();
                    showSuccess('Bond updated successfully!');
                    loadBonds();
                })
                .catch(function(error) { showError(error.message); });
        }

        function deleteBond() {
            if (!currentBondRow) return;
            var bondId = currentBondRow.getAttribute('data-bond-id');
            
            openDeleteModal('Are you sure you want to permanently delete this bond?', function() {
                apiFetch('/construction-bonds/' + bondId, { method: 'DELETE' })
                    .then(function() {
                        closeBondModal();
                        showSuccess('Bond deleted successfully!');
                        loadBonds();
                        currentBondRow = null;
                    })
                    .catch(function(error) { showError(error.message); });
            });
        }

        document.getElementById('bondDetailModal').addEventListener('click', function(e) {
            if (e.target === this) closeBondModal();
        });

        // ─── DEBUG: Check admin expenses ──────────────────────────────
        function debugAdminExpenses() {
            var month = document.getElementById('adminexpMonth').value || '2026-08';
            var period = month + '-01';
            
            console.log('=== DEBUG: Checking Admin Expenses ===');
            console.log('Period:', period);
            
            fetch(API_BASE + '/finance-expenses', {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            })
            .then(function(response) { return response.json(); })
            .then(function(expenses) {
                console.log('All expenses count:', expenses.length);
                
                var adminCategoryIds = financeCategories
                    .filter(function(c) {
                        return ADMIN_CATEGORY_CODES.indexOf(c.category_code) !== -1;
                    })
                    .map(function(c) { return c.fin_category_id || c.expense_category_id; });
                
                console.log('Admin category IDs:', adminCategoryIds);
                
                var adminExpenses = expenses.filter(function(e) {
                    return adminCategoryIds.indexOf(parseInt(e.fin_category_id || e.expense_category_id)) !== -1;
                });
                console.log('All admin expenses:', adminExpenses);
                
                var monthExpenses = adminExpenses.filter(function(e) {
                    return e.expense_date && e.expense_date.startsWith(month);
                });
                console.log('Admin expenses for ' + month + ':', monthExpenses);
                
                var tbody = document.getElementById('adminexpBody');
                if (monthExpenses.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="15" style="text-align:center;padding:20px;color:#f57c00;">🔍 No admin expenses found for ' + month + '. Check console for details.</td></tr>';
                    console.warn('No admin expenses found for ' + month);
                } else {
                    tbody.innerHTML = '<tr><td colspan="15" style="text-align:center;padding:20px;color:#2e7d32;">✅ Found ' + monthExpenses.length + ' admin expenses for ' + month + '. View console for details.</td></tr>';
                    // Also reload the admin exp view
                    loadAdminExp();
                }
            })
            .catch(function(error) {
                console.error('Debug error:', error);
                showError('Debug error: ' + error.message);
            });
        }

        // ─── REPORTS ───────────────────────────────────────────────────
        // EXPOVRALL - Load directly from fin_expense_tbl
        function loadExpovrall() {
            var month = document.getElementById('expovrallMonth').value;
            if (!month) {
                var now = new Date();
                month = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
                document.getElementById('expovrallMonth').value = month;
            }

            console.log('Loading EXPOVRALL for period:', month);

            // All categories in order
            var categoryCodes = ['CONST_SUPPLY', 'SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'RENT', 'STATIONERY', 'DEPRECIATION', 'REPAIR_MAINT', 'SSS_PHILHEALTH', 'OTHERS'];

            fetch(API_BASE + '/finance-expenses', {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Failed to fetch expenses');
                return response.json();
            })
            .then(function(expenses) {
                var categoryIds = financeCategories
                    .filter(function(c) {
                        return categoryCodes.indexOf(c.category_code) !== -1;
                    })
                    .map(function(c) { return c.fin_category_id || c.expense_category_id; });

                var filteredExpenses = expenses.filter(function(e) {
                    var catId = parseInt(e.fin_category_id || e.expense_category_id || 0);
                    var isMatch = categoryIds.indexOf(catId) !== -1;
                    var matchesMonth = e.expense_date && e.expense_date.startsWith(month);
                    return isMatch && matchesMonth;
                });

                var projects = {};
                filteredExpenses.forEach(function(e) {
                    var projectName = e.project_name || 'OFFICE';
                    if (!projects[projectName]) {
                        projects[projectName] = {};
                        categoryCodes.forEach(function(cat) {
                            projects[projectName][cat] = 0;
                        });
                        projects[projectName]['_total'] = 0;
                        projects[projectName]['_project_id'] = e.project_id || null;
                    }
                    var catId = parseInt(e.fin_category_id || e.expense_category_id || 0);
                    var category = financeCategories.find(function(c) {
                        return (c.fin_category_id || c.expense_category_id) == catId;
                    });
                    if (category) {
                        var code = category.category_code;
                        if (categoryCodes.indexOf(code) !== -1) {
                            var amount = parseFloat(e.amount) || 0;
                            projects[projectName][code] = (projects[projectName][code] || 0) + amount;
                            projects[projectName]['_total'] += amount;
                        }
                    }
                });

                var tbody = document.getElementById('expovrallBody');
                tbody.innerHTML = '';

                var projectNames = Object.keys(projects).sort();
                if (projectNames.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="14" style="text-align:center;padding:20px;">No data found for ' + month + '.</td></tr>';
                    return;
                }

                var grandTotal = 0;
                var categoryTotals = {};
                categoryCodes.forEach(function(cat) { categoryTotals[cat] = 0; });

                projectNames.forEach(function(projectName) {
                    var p = projects[projectName];
                    var tr = document.createElement('tr');
                    var cells = '<td><strong>' + projectName + '</strong></td>';
                    categoryCodes.forEach(function(cat) {
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
                var cells = '<td><strong>TOTAL</strong></td>';
                categoryCodes.forEach(function(cat) {
                    cells += '<td><strong>' + formatCurrency(categoryTotals[cat]) + '</strong></td>';
                });
                cells += '<td><strong>' + formatCurrency(grandTotal) + '</strong></td>';
                tr.innerHTML = cells;
                tbody.appendChild(tr);
            })
            .catch(function(error) {
                console.error('Error loading EXPOVRALL:', error);
                showError('Error loading EXPOVRALL: ' + error.message);
                document.getElementById('expovrallBody').innerHTML = '<tr><td colspan="14" style="text-align:center;padding:20px;color:#d32f2f;">Error loading data: ' + error.message + '</td></tr>';
            });
        }

        // HELPER: Centralized function to fetch expenses grouped by project and category for a specific month
        // Returns an object with direct_data, admin_data, and overall_data structures
        function fetchExpensesGroupedByProject(month, directCats, adminCats) {
            return new Promise(function(resolve, reject) {
                fetch(API_BASE + '/finance-expenses', {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                })
                .then(function(response) {
                    if (!response.ok) throw new Error('Failed to fetch expenses');
                    return response.json();
                })
                .then(function(expenses) {
                    // Mapped category IDs
                    var directCategoryIds = financeCategories
                        .filter(function(c) { return directCats.indexOf(c.category_code) !== -1; })
                        .map(function(c) { return c.fin_category_id || c.expense_category_id; });

                    var adminCategoryIds = financeCategories
                        .filter(function(c) { return adminCats.indexOf(c.category_code) !== -1; })
                        .map(function(c) { return c.fin_category_id || c.expense_category_id; });

                    // Filter by month
                    var monthExpenses = expenses.filter(function(e) {
                        return e.expense_date && e.expense_date.startsWith(month);
                    });

                    // Direct expenses for the month
                    var directExpenses = monthExpenses.filter(function(e) {
                        var catId = parseInt(e.fin_category_id || e.expense_category_id || 0);
                        return directCategoryIds.indexOf(catId) !== -1;
                    });

                    // Admin expenses for the month
                    var adminExpenses = monthExpenses.filter(function(e) {
                        var catId = parseInt(e.fin_category_id || e.expense_category_id || 0);
                        return adminCategoryIds.indexOf(catId) !== -1;
                    });

                    // --- Process Direct Expenses ---
                    var directProjects = {};
                    directExpenses.forEach(function(e) {
                        var projectName = e.project_name || 'OFFICE';
                        if (!directProjects[projectName]) {
                            directProjects[projectName] = {};
                            directCats.forEach(function(cat) {
                                directProjects[projectName][cat] = 0;
                            });
                            directProjects[projectName]['_total'] = 0;
                            directProjects[projectName]['_project_id'] = e.project_id || null;
                        }
                        var catId = parseInt(e.fin_category_id || e.expense_category_id || 0);
                        var category = financeCategories.find(function(c) {
                            return (c.fin_category_id || c.expense_category_id) == catId;
                        });
                        if (category) {
                            var code = category.category_code;
                            if (directCats.indexOf(code) !== -1) {
                                var amount = parseFloat(e.amount) || 0;
                                directProjects[projectName][code] = (directProjects[projectName][code] || 0) + amount;
                                directProjects[projectName]['_total'] += amount;
                            }
                        }
                    });

                    // --- Process Admin Expenses ---
                    var adminProjects = {};
                    adminExpenses.forEach(function(e) {
                        var projectName = e.project_name || 'OFFICE';
                        if (!adminProjects[projectName]) {
                            adminProjects[projectName] = {};
                            adminCats.forEach(function(cat) {
                                adminProjects[projectName][cat] = 0;
                            });
                            adminProjects[projectName]['_total'] = 0;
                            adminProjects[projectName]['_project_id'] = e.project_id || null;
                        }
                        var catId = parseInt(e.fin_category_id || e.expense_category_id || 0);
                        var category = financeCategories.find(function(c) {
                            return (c.fin_category_id || c.expense_category_id) == catId;
                        });
                        if (category) {
                            var code = category.category_code;
                            if (adminCats.indexOf(code) !== -1) {
                                var amount = parseFloat(e.amount) || 0;
                                adminProjects[projectName][code] = (adminProjects[projectName][code] || 0) + amount;
                                adminProjects[projectName]['_total'] += amount;
                            }
                        }
                    });

                    // --- Merge data for "Overall" ---
                    var allProjectNames = new Set(Object.keys(directProjects).concat(Object.keys(adminProjects)));
                    var overallProjects = {};
                    allProjectNames.forEach(function(projectName) {
                        overallProjects[projectName] = {};
                        var dProj = directProjects[projectName] || {};
                        var aProj = adminProjects[projectName] || {};
                        
                        // Copy direct totals
                        directCats.forEach(function(cat) {
                            overallProjects[projectName][cat] = (dProj[cat] || 0);
                        });
                        overallProjects[projectName]['_direct_total'] = dProj['_total'] || 0;
                        overallProjects[projectName]['_admin_total'] = aProj['_total'] || 0;
                        overallProjects[projectName]['_overall_total'] = (dProj['_total'] || 0) + (aProj['_total'] || 0);
                        overallProjects[projectName]['_project_id'] = dProj['_project_id'] || aProj['_project_id'] || null;
                    });

                    resolve({
                        directProjects: directProjects,
                        adminProjects: adminProjects,
                        overallProjects: overallProjects,
                        adminTotals: adminExpenses // Keep raw data for fallback
                    });
                })
                .catch(function(error) {
                    reject(error);
                });
            });
        }

        // EXP DIRECT - Uses centralized logic to ensure Admin Expense matches Admin Exp Tab Totals
        function loadExpDirect() {
            var month = document.getElementById('expdirectMonth').value;
            if (!month) {
                var now = new Date();
                month = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
                document.getElementById('expdirectMonth').value = month;
            }

            console.log('Loading EXP DIRECT for period:', month);

            var directCategoryCodes = ['CONST_SUPPLY', 'SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'OTHERS'];
            var adminCategoryCodes = ['RENT', 'STATIONERY', 'DEPRECIATION', 'REPAIR_MAINT', 'MISC', 'PENALTY', 'SSS_PHILHEALTH'];

            fetchExpensesGroupedByProject(month, directCategoryCodes, adminCategoryCodes)
                .then(function(groupedData) {
                    var directProjects = groupedData.directProjects;
                    var adminProjects = groupedData.adminProjects;

                    var tbody = document.getElementById('expdirectBody');
                    tbody.innerHTML = '';

                    var projectNames = Object.keys(directProjects).sort();
                    if (projectNames.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:20px;">No data found for ' + month + '.</td></tr>';
                        return;
                    }

                    var grandTotal = 0;
                    var totalAdmin = 0;
                    var categoryTotals = {};
                    directCategoryCodes.forEach(function(cat) { categoryTotals[cat] = 0; });

                    projectNames.forEach(function(projectName) {
                        var p = directProjects[projectName];
                        var a = adminProjects[projectName]; // Get admin data for same project

                        var tr = document.createElement('tr');
                        var cells = '<td><strong>' + projectName + '</strong></td>';
                        directCategoryCodes.forEach(function(cat) {
                            var val = p[cat] || 0;
                            cells += '<td>' + formatCurrency(val) + '</td>';
                            categoryTotals[cat] += val;
                        });
                        var adminTotal = (a ? a['_total'] : 0);
                        totalAdmin += adminTotal;
                        cells += '<td><strong>' + formatCurrency(p['_total']) + '</strong></td>';
                        cells += '<td>' + formatCurrency(adminTotal) + '</td>';
                        tr.innerHTML = cells;
                        tbody.appendChild(tr);
                        grandTotal += p['_total'];
                    });

                    // Handle projects with ONLY admin expenses but no direct expenses
                    // (They should show up as 'OFFICE' or their real name if admin-specific)
                    var onlyAdminProjects = Object.keys(adminProjects).filter(function(pName) {
                        return !directProjects[pName];
                    });
                    onlyAdminProjects.forEach(function(projectName) {
                        var a = adminProjects[projectName];
                        var tr = document.createElement('tr');
                        var cells = '<td><strong>' + projectName + '</strong></td>';
                        directCategoryCodes.forEach(function(cat) {
                            cells += '<td>' + formatCurrency(0) + '</td>';
                            categoryTotals[cat] += 0;
                        });
                        var adminTotal = a['_total'] || 0;
                        totalAdmin += adminTotal;
                        cells += '<td><strong>' + formatCurrency(0) + '</strong></td>';
                        cells += '<td>' + formatCurrency(adminTotal) + '</td>';
                        tr.innerHTML = cells;
                        tbody.appendChild(tr);
                    });

                    var tr = document.createElement('tr');
                    tr.className = 'total-row';
                    var cells = '<td><strong>TOTAL</strong></td>';
                    directCategoryCodes.forEach(function(cat) {
                        cells += '<td><strong>' + formatCurrency(categoryTotals[cat]) + '</strong></td>';
                    });
                    cells += '<td><strong>' + formatCurrency(grandTotal) + '</strong></td>';
                    cells += '<td><strong>' + formatCurrency(totalAdmin) + '</strong></td>';
                    tr.innerHTML = cells;
                    tbody.appendChild(tr);
                })
                .catch(function(error) {
                    console.error('Error loading EXP DIRECT:', error);
                    showError('Error loading EXP DIRECT: ' + error.message);
                    document.getElementById('expdirectBody').innerHTML = '<tr><td colspan="10" style="text-align:center;padding:20px;color:#d32f2f;">Error loading data: ' + error.message + '</td></tr>';
                });
        }

        // ADMIN EXP - Uses centralized logic
        function loadAdminExp() {
            var month = document.getElementById('adminexpMonth').value;
            if (!month) {
                var now = new Date();
                month = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
                document.getElementById('adminexpMonth').value = month;
            }

            console.log('Loading ADMIN EXP for period:', month);

            var directCategoryCodes = ['CONST_SUPPLY', 'SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'OTHERS'];
            var adminCategoryCodes = ['SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'RENT', 'STATIONERY', 'DEPRECIATION', 'REPAIR_MAINT', 'MISC', 'PENALTY', 'SSS_PHILHEALTH', 'OTHERS'];

            fetchExpensesGroupedByProject(month, directCategoryCodes, adminCategoryCodes)
                .then(function(groupedData) {
                    var adminProjects = groupedData.adminProjects;

                    var tbody = document.getElementById('adminexpBody');
                    tbody.innerHTML = '';

                    var projectNames = Object.keys(adminProjects).sort();
                    if (projectNames.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="15" style="text-align:center;padding:20px;">No admin expenses found for ' + month + '.</td></tr>';
                        return;
                    }

                    var grandTotal = 0;
                    var categoryTotals = {};
                    adminCategoryCodes.forEach(function(cat) { categoryTotals[cat] = 0; });

                    projectNames.forEach(function(projectName) {
                        var p = adminProjects[projectName];
                        var tr = document.createElement('tr');
                        var cells = '<td><strong>' + projectName + '</strong></td>';
                        adminCategoryCodes.forEach(function(cat) {
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
                    adminCategoryCodes.forEach(function(cat) {
                        cells += '<td><strong>' + formatCurrency(categoryTotals[cat]) + '</strong></td>';
                    });
                    cells += '<td><strong>' + formatCurrency(grandTotal) + '</strong></td>';
                    tr.innerHTML = cells;
                    tbody.appendChild(tr);

                    console.log('Admin expenses loaded successfully. Grand total:', grandTotal);
                })
                .catch(function(error) {
                    console.error('Error loading ADMIN EXP:', error);
                    showError('Error loading ADMIN EXP: ' + error.message);
                    document.getElementById('adminexpBody').innerHTML = '<tr><td colspan="15" style="text-align:center;padding:20px;color:#d32f2f;">Error loading data: ' + error.message + '</td></tr>';
                });
        }

        // DIRECT EXP - Uses centralized logic
        function loadDirectExp() {
            var month = document.getElementById('directexpMonth').value;
            if (!month) {
                var now = new Date();
                month = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
                document.getElementById('directexpMonth').value = month;
            }

            console.log('Loading DIRECT EXP for period:', month);

            var directCategoryCodes = ['CONST_SUPPLY', 'SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'OTHERS'];
            var adminCategoryCodes = ['RENT', 'STATIONERY', 'DEPRECIATION', 'REPAIR_MAINT', 'MISC', 'PENALTY', 'SSS_PHILHEALTH'];

            fetchExpensesGroupedByProject(month, directCategoryCodes, adminCategoryCodes)
                .then(function(groupedData) {
                    var directProjects = groupedData.directProjects;
                    var adminProjects = groupedData.adminProjects;

                    var tbody = document.getElementById('directexpBody');
                    tbody.innerHTML = '';

                    var projectNames = Object.keys(directProjects).sort();
                    if (projectNames.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:20px;">No data found for ' + month + '.</td></tr>';
                        return;
                    }

                    var grandTotal = 0;
                    var totalAdmin = 0;
                    var categoryTotals = {};
                    directCategoryCodes.forEach(function(cat) { categoryTotals[cat] = 0; });

                    projectNames.forEach(function(projectName) {
                        var p = directProjects[projectName];
                        var a = adminProjects[projectName];

                        var tr = document.createElement('tr');
                        var cells = '<td><strong>' + projectName + '</strong></td>';
                        directCategoryCodes.forEach(function(cat) {
                            var val = p[cat] || 0;
                            cells += '<td>' + formatCurrency(val) + '</td>';
                            categoryTotals[cat] += val;
                        });
                        var adminTotal = (a ? a['_total'] : 0);
                        totalAdmin += adminTotal;
                        cells += '<td><strong>' + formatCurrency(p['_total']) + '</strong></td>';
                        cells += '<td>' + formatCurrency(adminTotal) + '</td>';
                        tr.innerHTML = cells;
                        tbody.appendChild(tr);
                        grandTotal += p['_total'];
                    });

                    var onlyAdminProjects = Object.keys(adminProjects).filter(function(pName) {
                        return !directProjects[pName];
                    });
                    onlyAdminProjects.forEach(function(projectName) {
                        var a = adminProjects[projectName];
                        var tr = document.createElement('tr');
                        var cells = '<td><strong>' + projectName + '</strong></td>';
                        directCategoryCodes.forEach(function(cat) {
                            cells += '<td>' + formatCurrency(0) + '</td>';
                            categoryTotals[cat] += 0;
                        });
                        var adminTotal = a['_total'] || 0;
                        totalAdmin += adminTotal;
                        cells += '<td><strong>' + formatCurrency(0) + '</strong></td>';
                        cells += '<td>' + formatCurrency(adminTotal) + '</td>';
                        tr.innerHTML = cells;
                        tbody.appendChild(tr);
                    });

                    var tr = document.createElement('tr');
                    tr.className = 'total-row';
                    var cells = '<td><strong>TOTAL</strong></td>';
                    directCategoryCodes.forEach(function(cat) {
                        cells += '<td><strong>' + formatCurrency(categoryTotals[cat]) + '</strong></td>';
                    });
                    cells += '<td><strong>' + formatCurrency(grandTotal) + '</strong></td>';
                    cells += '<td><strong>' + formatCurrency(totalAdmin) + '</strong></td>';
                    tr.innerHTML = cells;
                    tbody.appendChild(tr);
                })
                .catch(function(error) {
                    console.error('Error loading DIRECT EXP:', error);
                    showError('Error loading DIRECT EXP: ' + error.message);
                    document.getElementById('directexpBody').innerHTML = '<tr><td colspan="10" style="text-align:center;padding:20px;color:#d32f2f;">Error loading data: ' + error.message + '</td></tr>';
                });
        }

        // OVERALL EXP - Uses centralized logic
        function loadOverallExp() {
            var month = document.getElementById('overallexpMonth').value;
            if (!month) {
                var now = new Date();
                month = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
                document.getElementById('overallexpMonth').value = month;
            }

            console.log('Loading OVERALL EXP for period:', month);

            var directCategoryCodes = ['CONST_SUPPLY', 'SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'OTHERS'];
            var adminCategoryCodes = ['RENT', 'STATIONERY', 'DEPRECIATION', 'REPAIR_MAINT', 'MISC', 'PENALTY', 'SSS_PHILHEALTH'];

            fetchExpensesGroupedByProject(month, directCategoryCodes, adminCategoryCodes)
                .then(function(groupedData) {
                    var overallProjects = groupedData.overallProjects;

                    var tbody = document.getElementById('overallexpBody');
                    tbody.innerHTML = '';

                    var projectNames = Object.keys(overallProjects).sort();
                    if (projectNames.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:20px;">No data found for ' + month + '.</td></tr>';
                        return;
                    }

                    var grandTotal = 0;
                    var totalAdmin = 0;
                    var categoryTotals = {};
                    directCategoryCodes.forEach(function(cat) { categoryTotals[cat] = 0; });

                    projectNames.forEach(function(projectName) {
                        var p = overallProjects[projectName];
                        var tr = document.createElement('tr');
                        var cells = '<td><strong>' + projectName + '</strong></td>';
                        directCategoryCodes.forEach(function(cat) {
                            var val = p[cat] || 0;
                            cells += '<td>' + formatCurrency(val) + '</td>';
                            categoryTotals[cat] += val;
                        });
                        var admin = p['_admin_total'] || 0;
                        totalAdmin += admin;
                        var overallTotal = p['_overall_total'] || 0;
                        cells += '<td>' + formatCurrency(admin) + '</td>';
                        cells += '<td><strong>' + formatCurrency(overallTotal) + '</strong></td>';
                        tr.innerHTML = cells;
                        tbody.appendChild(tr);
                        grandTotal += overallTotal;
                    });

                    var tr = document.createElement('tr');
                    tr.className = 'total-row';
                    var cells = '<td><strong>TOTAL OVERALL EXPENSES</strong></td>';
                    directCategoryCodes.forEach(function(cat) {
                        cells += '<td><strong>' + formatCurrency(categoryTotals[cat]) + '</strong></td>';
                    });
                    cells += '<td><strong>' + formatCurrency(totalAdmin) + '</strong></td>';
                    cells += '<td><strong>' + formatCurrency(grandTotal) + '</strong></td>';
                    tr.innerHTML = cells;
                    tbody.appendChild(tr);
                })
                .catch(function(error) {
                    console.error('Error loading OVERALL EXP:', error);
                    showError('Error loading OVERALL EXP: ' + error.message);
                    document.getElementById('overallexpBody').innerHTML = '<tr><td colspan="10" style="text-align:center;padding:20px;color:#d32f2f;">Error loading data: ' + error.message + '</td></tr>';
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
                        tbody.innerHTML = '<tr><td colspan="13" style="text-align:center;padding:20px;">No data found. Please add budgets to projects.</td></tr>';
                        return;
                    }

                    var hasData = false;
                    data.forEach(function(row) {
                        var contractPrice = parseFloat(row.original_contract_price) || 0;
                        // Skip rows with zero contract price and no contract
                        if (contractPrice === 0 && parseInt(row.contract_id) === 0) {
                            return;
                        }
                        hasData = true;
                        var tr = document.createElement('tr');
                        var profitPayment = parseFloat(row.profit_loss_payment_basis) || 0;
                        var profitContract = parseFloat(row.profit_loss_contract_basis) || 0;
                        tr.setAttribute('data-project-id', row.project_id);
                        tr.setAttribute('data-contract-id', row.contract_id || '');
                        tr.setAttribute('data-contract-price', contractPrice);
                        tr.setAttribute('data-addl-works', row.additional_works_contract || 0);
                        tr.setAttribute('data-payment', row.original_payment_received || 0);
                        tr.setAttribute('data-addl-payment', row.additional_works_payment || 0);
                        tr.setAttribute('data-remarks', row.remarks || '');
                        tr.style.cursor = 'pointer';
                        tr.onclick = function() { openAddContractModal(this); };
                        tr.innerHTML = '<td><strong>' + (row.project_name || '') + '</strong></td>' +
                            '<td>' + (row.start_date || '') + '</td>' +
                            '<td>' + (row.actual_end_date || 'In Progress') + '</td>' +
                            '<td>' + formatCurrency(contractPrice) + '</td>' +
                            '<td>' + formatCurrency(row.additional_works_contract || 0) + '</td>' +
                            '<td>' + formatCurrency(row.total_contract_price || 0) + '</td>' +
                            '<td>' + formatCurrency(row.original_payment_received || 0) + '</td>' +
                            '<td>' + formatCurrency(row.additional_works_payment || 0) + '</td>' +
                            '<td>' + formatCurrency(row.total_payment || 0) + '</td>' +
                            '<td>' + formatCurrency(row.project_expense || 0) + '</td>' +
                            '<td>' + formatCurrency(row.accounts_receivable || 0) + '</td>' +
                            '<td class="' + (profitPayment < 0 ? 'amount-negative' : 'amount-positive') + '">' + formatCurrency(profitPayment) + '</td>' +
                            '<td class="' + (profitContract < 0 ? 'amount-negative' : 'amount-positive') + '">' + formatCurrency(profitContract) + '</td>';
                        tbody.appendChild(tr);
                    });

                    if (!hasData) {
                        tbody.innerHTML = '<tr><td colspan="13" style="text-align:center;padding:20px;">No projects with budgets found. Please add budgets to projects.</td></tr>';
                    }
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
                        // Get amounts from the individual columns
                        var amt30d = parseFloat(row.amount_30d) || 0;
                        var amt60d = parseFloat(row.amount_31_60d) || 0;
                        var amt90d = parseFloat(row.amount_61_90d) || 0;
                        var amt120d = parseFloat(row.amount_91_120d) || 0;
                        // Calculate total from the individual amounts
                        var rTotal = amt30d + amt60d + amt90d + amt120d;
                        
                        tr.setAttribute('data-rp-id', row.rp_id);
                        tr.setAttribute('data-type', row.entry_type || '');
                        tr.setAttribute('data-counterparty', row.counterparty_name || '');
                        tr.setAttribute('data-project', row.project_name || '');
                        tr.setAttribute('data-project-id', row.project_id || '');
                        tr.setAttribute('data-date', row.entry_date || '');
                        tr.setAttribute('data-amount30d', amt30d);
                        tr.setAttribute('data-amount60d', amt60d);
                        tr.setAttribute('data-amount90d', amt90d);
                        tr.setAttribute('data-amount120d', amt120d);
                        tr.setAttribute('data-status', row.status || 'outstanding');
                        tr.setAttribute('data-remarks', row.remarks || '');
                        tr.style.cursor = 'pointer';
                        tr.onclick = function() { openReceivableModal(this); };

                        tr.innerHTML = '<td>' + (row.entry_date || '') + '</td>' +
                            '<td>' + (row.counterparty_name || '') + '</td>' +
                            '<td>' + (row.project_name || '—') + '</td>' +
                            '<td>' + formatCurrency(amt30d) + '</td>' +
                            '<td>' + formatCurrency(amt60d) + '</td>' +
                            '<td>' + formatCurrency(amt90d) + '</td>' +
                            '<td>' + formatCurrency(amt120d) + '</td>' +
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
                        tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;padding:20px;">No data found for this period.</td></tr>';
                        return;
                    }

                    var total = 0;
                    data.forEach(function(row) {
                        var tr = document.createElement('tr');
                        var accountName = row.account_name || 'Account ' + row.account_id;
                        tr.setAttribute('data-cash-id', row.cash_position_id);
                        tr.setAttribute('data-account-id', row.account_id);
                        tr.setAttribute('data-account-name', accountName);
                        tr.setAttribute('data-balance', row.balance_amount || 0);
                        tr.setAttribute('data-period', row.period_month || '');
                        tr.style.cursor = 'pointer';
                        tr.onclick = function() { openCashModal(this); };

                        tr.innerHTML = '<td><strong>' + accountName + '</strong></td>' +
                            '<td>' + formatCurrency(row.balance_amount || 0) + '</td>';
                        tbody.appendChild(tr);
                        total += parseFloat(row.balance_amount) || 0;
                    });

                    var tr = document.createElement('tr');
                    tr.className = 'total-row';
                    tr.innerHTML = '<td><strong>TOTAL CASH ASSET</strong></td><td><strong>' + formatCurrency(total) + '</strong></td>';
                    tbody.appendChild(tr);
                })
                .catch(function(error) {
                    showError('Error loading Cash Asset: ' + error.message);
                });
        }

        // ─── CASH ASSET DETAIL MODAL ──────────────────────────────────
        var currentCashRow = null;
        var isCashEditMode = false;

        function openCashModal(row) {
            currentCashRow = row;
            document.getElementById('cashModalTitle').textContent = 'Cash Position Details';
            
            // Get data from the row
            var accountName = row.dataset.accountName || '';
            var period = row.dataset.period || '';
            var balance = parseFloat(row.dataset.balance) || 0;
            
            // Display values (read-only mode)
            document.getElementById('cashDetailAccount').textContent = accountName;
            document.getElementById('cashDetailPeriod').textContent = period ? new Date(period).toLocaleDateString('en-US', { year: 'numeric', month: '2-digit' }) : '—';
            document.getElementById('cashDetailBalance').textContent = formatCurrency(balance);
            
            // Set edit values
            document.getElementById('cashDetailAccountEdit').value = row.dataset.accountId || '';
            document.getElementById('cashDetailPeriodEdit').value = period ? period.substring(0, 7) : '';
            document.getElementById('cashDetailBalanceEdit').value = balance;
            
            // Store account name for display in edit mode
            document.getElementById('cashDetailAccountDisplay').textContent = accountName;

            if (isCashEditMode) toggleCashEdit();
            isCashEditMode = false;
            document.getElementById('cashDetailEditBtn').style.display = 'inline-block';
            document.getElementById('cashDetailDeleteBtn').style.display = 'inline-block';
            document.getElementById('cashDetailSaveBtn').style.display = 'none';
            
            // Reset display/edit visibility
            document.querySelectorAll('#cashDetailModal .detail-edit').forEach(function(el) { 
                el.style.display = 'none'; 
            });
            document.querySelectorAll('#cashDetailModal .detail-value').forEach(function(el) { 
                el.style.display = ''; 
            });
            document.getElementById('cashDetailAccountDisplay').style.display = 'none';

            document.getElementById('cashDetailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeCashModal() {
            document.getElementById('cashDetailModal').classList.remove('active');
            document.body.style.overflow = '';
            if (isCashEditMode) toggleCashEdit();
        }

        function toggleCashEdit() {
            isCashEditMode = !isCashEditMode;
            var editBtn = document.getElementById('cashDetailEditBtn');
            var deleteBtn = document.getElementById('cashDetailDeleteBtn');
            var saveBtn = document.getElementById('cashDetailSaveBtn');
            
            // Editable fields
            var editableFields = ['cashDetailPeriodEdit', 'cashDetailBalanceEdit'];
            
            // Display only fields (account is not editable)
            var displayFields = ['cashDetailAccount', 'cashDetailPeriod', 'cashDetailBalance'];

            if (isCashEditMode) {
                editBtn.style.display = 'none';
                deleteBtn.style.display = 'none';
                saveBtn.style.display = 'inline-block';
                
                // Hide display values
                displayFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.style.display = 'none';
                });
                
                // Show edit fields
                editableFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.style.display = '';
                });
                
                // Show account as read-only display
                document.getElementById('cashDetailAccountDisplay').style.display = '';
                // Hide account edit select
                document.getElementById('cashDetailAccountEdit').style.display = 'none';
                
            } else {
                editBtn.style.display = 'inline-block';
                deleteBtn.style.display = 'inline-block';
                saveBtn.style.display = 'none';
                
                // Show display values
                displayFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.style.display = '';
                });
                
                // Hide edit fields
                editableFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.style.display = 'none';
                });
                
                document.getElementById('cashDetailAccountDisplay').style.display = 'none';
            }
        }

        function saveCashDetail() {
            if (!currentCashRow) return;
            var cashId = currentCashRow.getAttribute('data-cash-id');
            var period = document.getElementById('cashDetailPeriodEdit').value;
            var balance = parseFloat(document.getElementById('cashDetailBalanceEdit').value) || 0;

            if (!period) {
                showError('Please select a month.');
                return;
            }
            if (balance < 0) {
                showError('Balance must be greater than or equal to 0.');
                return;
            }

            var payload = {
                period_month: period + '-01',
                balance_amount: balance
            };

            apiFetch('/cash-positions/' + cashId, { method: 'PUT', body: JSON.stringify(payload) })
                .then(function() {
                    closeCashModal();
                    showSuccess('Cash position updated successfully!');
                    loadCashAsset();
                })
                .catch(function(error) { showError(error.message); });
        }

        function deleteCashPosition() {
            if (!currentCashRow) return;
            var cashId = currentCashRow.getAttribute('data-cash-id');
            
            openDeleteModal('Are you sure you want to permanently delete this cash position?', function() {
                apiFetch('/cash-positions/' + cashId, { method: 'DELETE' })
                    .then(function() {
                        closeCashModal();
                        showSuccess('Cash position deleted successfully!');
                        loadCashAsset();
                        currentCashRow = null;
                    })
                    .catch(function(error) { showError(error.message); });
            });
        }

        document.getElementById('cashDetailModal').addEventListener('click', function(e) {
            if (e.target === this) closeCashModal();
        });

        // REPAIR
        function loadRepair() {
            var month = document.getElementById('repairMonth').value;
            if (!month) return;
            var period = month + '-01';

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
                        tr.setAttribute('data-repair-id', row.equip_expense_id);
                        tr.setAttribute('data-asset-id', row.asset_id);
                        tr.setAttribute('data-asset-name', row.asset_name || 'Unknown Asset');
                        tr.setAttribute('data-expense-type', expenseType);
                        tr.setAttribute('data-amount', row.asset_total || row.amount || 0);
                        tr.setAttribute('data-date', row.period_month || row.expense_date || '');
                        tr.setAttribute('data-remarks', row.remarks || '');
                        tr.style.cursor = 'pointer';
                        tr.onclick = function() { openRepairModal(this); };

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

        // ─── REPAIR DETAIL MODAL (Placeholder) ──────────────────────
        function openRepairModal(row) {
            showSuccess('Repair details for: ' + row.dataset.assetName);
        }

        // BACKHOE
        function loadBackhoe() {
            var assetId = document.getElementById('backhoeAsset').value;
            var month = document.getElementById('backhoeMonth').value;
            
            // Build URL with filters
            var url = '/reports/backhoe-profitability';
            var params = [];
            if (assetId) {
                params.push('asset_id=' + assetId);
            }
            if (month) {
                params.push('period=' + month + '-01');
            }
            if (params.length > 0) {
                url += '?' + params.join('&');
            }

            apiFetch(url)
                .then(function(data) {
                    var tbody = document.getElementById('backhoeBody');
                    tbody.innerHTML = '';

                    if (!data || !data.length) {
                        tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;padding:20px;">No data found.</td></tr>';
                        return;
                    }

                    // Group by asset and period, with expense type breakdown
                    var assetPeriodData = {};
                    data.forEach(function(row) {
                        var key = row.asset_id + '|' + (row.period_month || '');
                        if (!assetPeriodData[key]) {
                            assetPeriodData[key] = {
                                asset_id: row.asset_id,
                                asset_name: row.asset_name || 'Unknown',
                                period_month: row.period_month || '',
                                gas_diesel: 0,
                                payroll_operator: 0,
                                repair: 0,
                                other: 0,
                                delivery: 0,
                                transportation: 0,
                                total_expense: 0,
                                rental_income: 0,
                                net_income: 0
                            };
                        }
                        // Sum by expense type
                        var expenseType = row.expense_type || '';
                        var amount = parseFloat(row.amount) || 0;
                        if (expenseType === 'gas_diesel') {
                            assetPeriodData[key].gas_diesel += amount;
                        } else if (expenseType === 'payroll_operator') {
                            assetPeriodData[key].payroll_operator += amount;
                        } else if (expenseType === 'repair') {
                            assetPeriodData[key].repair += amount;
                        } else if (expenseType === 'other') {
                            assetPeriodData[key].other += amount;
                        } else if (expenseType === 'delivery') {
                            assetPeriodData[key].delivery += amount;
                        } else if (expenseType === 'transportation') {
                            assetPeriodData[key].transportation += amount;
                        }
                        // Update totals
                        assetPeriodData[key].total_expense += amount;
                        // Rental income and net income from the row
                        if (row.rental_income) {
                            assetPeriodData[key].rental_income = parseFloat(row.rental_income) || 0;
                        }
                        if (row.net_income) {
                            assetPeriodData[key].net_income = parseFloat(row.net_income) || 0;
                        }
                    });

                    var grandExpense = 0;
                    var grandIncome = 0;
                    var grandNet = 0;

                    Object.keys(assetPeriodData).forEach(function(key) {
                        var a = assetPeriodData[key];
                        var tr = document.createElement('tr');
                        var net = a.net_income;
                        
                        tr.innerHTML = '<td><strong>' + a.asset_name + '</strong></td>' +
                            '<td>' + (a.period_month || '') + '</td>' +
                            '<td>' + formatCurrency(a.gas_diesel) + '</td>' +
                            '<td>' + formatCurrency(a.payroll_operator) + '</td>' +
                            '<td>' + formatCurrency(a.repair) + '</td>' +
                            '<td>' + formatCurrency(a.other) + '</td>' +
                            '<td>' + formatCurrency(a.delivery) + '</td>' +
                            '<td>' + formatCurrency(a.transportation) + '</td>' +
                            '<td><strong>' + formatCurrency(a.total_expense) + '</strong></td>' +
                            '<td>' + formatCurrency(a.rental_income) + '</td>' +
                            '<td class="' + (net < 0 ? 'amount-negative' : 'amount-positive') + '">' + formatCurrency(net) + '</td>';
                        tbody.appendChild(tr);
                        grandExpense += a.total_expense;
                        grandIncome += a.rental_income;
                        grandNet += net;
                    });

                    // Add grand total row
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
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;">No bonds found.</td></tr>';
                        return;
                    }

                    var total = 0;
                    data.forEach(function(row) {
                        var tr = document.createElement('tr');
                        var statusClass = 'status-' + (row.status || 'active');
                        
                        // Format date properly
                        var dateFormatted = '—';
                        if (row.bond_date) {
                            var dateObj = new Date(row.bond_date);
                            if (!isNaN(dateObj.getTime())) {
                                dateFormatted = dateObj.toLocaleDateString('en-US', {
                                    year: 'numeric',
                                    month: '2-digit',
                                    day: '2-digit'
                                });
                            }
                        }
                        
                        // Get project name from the relation or fallback
                        var projectName = row.project ? row.project.project_name : (row.project_name || 'Project ' + row.project_id);
                        
                        tr.setAttribute('data-bond-id', row.bond_id);
                        tr.setAttribute('data-project-id', row.project_id);
                        tr.setAttribute('data-project-name', projectName);
                        tr.setAttribute('data-date', row.bond_date || '');
                        tr.setAttribute('data-amount', row.amount || 0);
                        tr.setAttribute('data-provider', row.bond_provider || '');
                        tr.setAttribute('data-status', row.status || 'active');
                        tr.setAttribute('data-remarks', row.remarks || '');
                        tr.style.cursor = 'pointer';
                        tr.onclick = function() { openBondModal(this); };

                        tr.innerHTML = '<td>' + dateFormatted + '</td>' +
                            '<td>' + projectName + '</td>' +
                            '<td>' + (row.bond_provider || '—') + '</td>' +
                            '<td>' + formatCurrency(row.amount || 0) + '</td>' +
                            '<td><span class="' + statusClass + '">' + (row.status || 'active') + '</span></td>';
                        tbody.appendChild(tr);
                        total += parseFloat(row.amount) || 0;
                    });

                    var tr = document.createElement('tr');
                    tr.className = 'total-row';
                    tr.innerHTML = '<td colspan="3"><strong>GRAND TOTAL</strong></td>' +
                        '<td><strong>' + formatCurrency(total) + '</strong></td>' +
                        '<td></td>';
                    tbody.appendChild(tr);
                })
                .catch(function(error) {
                    showError('Error loading Bonds: ' + error.message);
                });
        }

        // ─── BOND DETAIL MODAL ──────────────────────────────────────
        var currentBondRow = null;
        var isBondEditMode = false;

        function openBondModal(row) {
            currentBondRow = row;
            document.getElementById('bondModalTitle').textContent = 'Bond Details';
            
            // Get data from the row
            var projectName = row.dataset.projectName || '';
            var date = row.dataset.date || '';
            var amount = parseFloat(row.dataset.amount) || 0;
            var provider = row.dataset.provider || '';
            var status = row.dataset.status || 'active';
            var remarks = row.dataset.remarks || '';
            
            // Display values (read-only mode)
            document.getElementById('bondDetailProject').textContent = projectName;
            document.getElementById('bondDetailDate').textContent = date ? new Date(date).toLocaleDateString() : '—';
            document.getElementById('bondDetailAmount').textContent = formatCurrency(amount);
            document.getElementById('bondDetailProvider').textContent = provider || '—';
            document.getElementById('bondDetailStatus').textContent = status || 'active';
            document.getElementById('bondDetailRemarks').textContent = remarks || '—';
            
            // Set edit values
            document.getElementById('bondDetailProjectEdit').value = row.dataset.projectId || '';
            document.getElementById('bondDetailDateEdit').value = date || '';
            document.getElementById('bondDetailAmountEdit').value = amount;
            document.getElementById('bondDetailProviderEdit').value = provider || '';
            document.getElementById('bondDetailStatusEdit').value = status || 'active';
            document.getElementById('bondDetailRemarksEdit').value = remarks || '';
            
            // Store project name for display in edit mode
            document.getElementById('bondDetailProjectDisplay').textContent = projectName;

            if (isBondEditMode) toggleBondEdit();
            isBondEditMode = false;
            document.getElementById('bondDetailEditBtn').style.display = 'inline-block';
            document.getElementById('bondDetailDeleteBtn').style.display = 'inline-block';
            document.getElementById('bondDetailSaveBtn').style.display = 'none';
            
            // Reset display/edit visibility
            document.querySelectorAll('#bondDetailModal .detail-edit').forEach(function(el) { 
                el.style.display = 'none'; 
            });
            document.querySelectorAll('#bondDetailModal .detail-value').forEach(function(el) { 
                el.style.display = ''; 
            });
            document.getElementById('bondDetailProjectDisplay').style.display = 'none';

            document.getElementById('bondDetailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeBondModal() {
            document.getElementById('bondDetailModal').classList.remove('active');
            document.body.style.overflow = '';
            if (isBondEditMode) toggleBondEdit();
        }

        function toggleBondEdit() {
            isBondEditMode = !isBondEditMode;
            var editBtn = document.getElementById('bondDetailEditBtn');
            var deleteBtn = document.getElementById('bondDetailDeleteBtn');
            var saveBtn = document.getElementById('bondDetailSaveBtn');
            
            // Editable fields
            var editableFields = ['bondDetailDateEdit', 'bondDetailAmountEdit', 'bondDetailProviderEdit', 
                                  'bondDetailStatusEdit', 'bondDetailRemarksEdit'];
            
            // Display only fields (project is not editable)
            var displayFields = ['bondDetailProject', 'bondDetailDate', 'bondDetailAmount', 
                                 'bondDetailProvider', 'bondDetailStatus', 'bondDetailRemarks'];

            if (isBondEditMode) {
                editBtn.style.display = 'none';
                deleteBtn.style.display = 'none';
                saveBtn.style.display = 'inline-block';
                
                // Hide display values
                displayFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.style.display = 'none';
                });
                
                // Show edit fields
                editableFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.style.display = '';
                });
                
                // Show project as read-only display
                document.getElementById('bondDetailProjectDisplay').style.display = '';
                // Hide project edit select
                document.getElementById('bondDetailProjectEdit').style.display = 'none';
                
            } else {
                editBtn.style.display = 'inline-block';
                deleteBtn.style.display = 'inline-block';
                saveBtn.style.display = 'none';
                
                // Show display values
                displayFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.style.display = '';
                });
                
                // Hide edit fields
                editableFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.style.display = 'none';
                });
                
                document.getElementById('bondDetailProjectDisplay').style.display = 'none';
            }
        }

        // SUMMARY
        function loadSummary() {
            var month = document.getElementById('summaryMonth').value;
            if (!month) return;
            var period = month + '-01';

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
            var modals = ['addExpenseModal', 'addBudgetModal', 'addContractModal',
                          'addReceivableModal', 'addCashModal', 'addRepairModal', 'addBackhoeExpenseModal',
                          'addBackhoeRentalModal', 'addBondModal', 'expenseDetailModal', 'budgetDetailModal',
                          'receivableDetailModal', 'bondDetailModal', 'cashDetailModal'];
            modals.forEach(function(id) {
                var modal = document.getElementById(id);
                if (modal && e.target === modal) {
                    var closeFn = {
                        'addExpenseModal': closeAddExpenseModal,
                        'addBudgetModal': closeAddBudgetModal,
                        'addContractModal': closeAddContractModal,
                        'addReceivableModal': closeAddReceivableModal,
                        'addCashModal': closeAddCashModal,
                        'addRepairModal': closeAddRepairModal,
                        'addBackhoeExpenseModal': closeAddBackhoeExpenseModal,
                        'addBackhoeRentalModal': closeAddBackhoeRentalModal,
                        'addBondModal': closeAddBondModal,
                        'expenseDetailModal': closeExpenseDetailModal,
                        'budgetDetailModal': closeBudgetDetailModal,
                        'receivableDetailModal': closeReceivableModal,
                        'bondDetailModal': closeBondModal,
                        'cashDetailModal': closeCashModal
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
                    if (currentReportTab === 'budgets') {
                        fetchBudgetData();
                    }
                    // Load admin exp if on that tab
                    if (currentReportTab === 'adminexp') {
                        loadAdminExp();
                    }
                });
            initializeProofFileUpload();
        });
    </script>

    @include('partials.data-import', ['importModule' => 'finance'])
    <script src="{{ asset('js/finance-analytics.js') }}"></script>
</body>
</html>
