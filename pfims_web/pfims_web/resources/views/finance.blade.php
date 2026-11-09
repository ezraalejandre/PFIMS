{{-- Prepare the values used by this page section. --}}
@php
    $portal = $portal ?? 'admin';
    $financeSection = request()->query('section');
    $financeTab = match ($financeSection) {
        'budgets' => 'budgets',
        'ar-ap' => 'receivables',
        'cash-position' => 'cash',
        'equipment' => 'backhoe',
        'bonds' => 'bonds',
        'summary' => 'summary',
        default => 'expenses',
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Budget & Finance - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/finance.css') }}?v={{ filemtime(public_path('css/finance.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/module-analytics.css') }}">
    {{-- Set the styles used by this page section. --}}
    <style>
        /* Modal z-index */
        #deleteConfirmModal { z-index: 9999 !important; }
        /* Style the delete budget confirm modal. */
        #deleteBudgetConfirmModal { z-index: 9999 !important; }
        /* Style the delete bond confirm modal. */
        #deleteBondConfirmModal { z-index: 9999 !important; }

        /* Style the report section. */
        .report-section { display: none; }
        /* Style the report section active. */
        .report-section.active { display: block; }
        /* Style the finance header actions. */
        #financeHeaderActions[hidden] { display: none !important; }
        /* Style the finance header action group. */
        .finance-header-action-group { display: flex; gap: 10px; flex-wrap: wrap; }
        /* Style the finance header action group. */
        .finance-header-action-group[hidden] { display: none !important; }

        /* Style the report table wrapper. */
        .report-table-wrapper {
            overflow-x: auto;
            margin-top: 15px;
            background: #fff;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        /* Style the report table wrapper. */
        .report-table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            min-width: 600px;
        }
        /* Style the report table wrapper. */
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
        /* Style the report table wrapper. */
        .report-table-wrapper table td {
            padding: 6px 10px;
            border-bottom: 1px solid #f0f0f0;
            white-space: nowrap;
        }
        /* Style the report table wrapper on hover. */
        .report-table-wrapper table tr:hover { background: #faf8f5; }
        /* Style the report table wrapper total row. */
        .report-table-wrapper table .total-row {
            font-weight: 700;
            background: #f8f6f3;
            border-top: 2px solid #ddd;
        }
        /* Style the report table wrapper sub total row. */
        .report-table-wrapper table .sub-total-row {
            font-weight: 600;
            background: #faf8f5;
            border-top: 1px solid #e9ecef;
        }

        /* Style the amount positive. */
        .amount-positive { color: #2e7d32; }
        /* Style the amount negative. */
        .amount-negative { color: #d32f2f; font-weight: 600; }
        /* Style the amount warning. */
        .amount-warning { color: #f57c00; }
        /* Style the budget overrun. */
        .budget-overrun { background: #ffebee !important; }
        /* Style the budget warning. */
        .budget-warning { background: #fff3e0 !important; }
        /* Style the status active. */
        .status-active { color: #2e7d32; }
        /* Style the status released. */
        .status-released { color: #f57c00; }
        /* Style the status forfeited. */
        .status-forfeited { color: #d32f2f; }

        /* Style the badge. */
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        /* Style the badge success. */
        .badge-success { background: #e8f5e9; color: #2e7d32; }
        /* Style the badge warning. */
        .badge-warning { background: #fff3e0; color: #f57c00; }
        /* Style the badge danger. */
        .badge-danger { background: #ffebee; color: #d32f2f; }
        /* Style the badge secondary. */
        .badge-secondary { background: #f5f5f5; color: #888; }
        /* Style the badge info. */
        .badge-info { background: #e3f2fd; color: #1565c0; }

        /* Style the filter row. */
        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 20px;
        }
        /* Style the filter row. */
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
        /* Style the filter row when focused. */
        .filter-row select:focus,
        .filter-row input:focus {
            border-color: #c9a96e;
            box-shadow: 0 0 0 3px rgba(201,169,110,0.2);
        }
        /* Style the btn clear search. */
        .btn-clear-search {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 20px;
            background: #fff;
            cursor: pointer;
            color: #888;
            transition: 0.3s;
        }
        /* Style the btn clear search on hover. */
        .btn-clear-search:hover {
            background: #f5f5f5;
            border-color: #c9a96e;
            color: #333;
        }

        /* Style the btn add data. */
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
        /* Style the btn add data on hover. */
        .btn-add-data:hover {
            background: #2a3f54;
            transform: translateY(-1px);
        }
        /* Style the btn add data gold. */
        .btn-add-data.gold {
            background: #c9a96e;
        }
        /* Style the btn add data gold on hover. */
        .btn-add-data.gold:hover {
            background: #b8975a;
        }

        /* Style the stats row. */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
            max-width: 700px;
        }
        /* Style the stat mini. */
        .stat-mini {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        /* Style the stat mini stat label. */
        .stat-mini .stat-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            margin-bottom: 4px;
        }
        /* Style the stat mini stat value. */
        .stat-mini .stat-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1a2b3c;
        }
        /* Style the stat value blue. */
        .stat-mini .stat-value.blue { color: #c9a96e; }
        /* Style the stat value red. */
        .stat-mini .stat-value.red { color: #d32f2f; }
        /* Style the stat value green. */
        .stat-mini .stat-value.green { color: #2e7d32; }

        /* Style the stats row budget. */
        .stats-row-budget {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-bottom: 25px;
            max-width: 200px;
        }
        /* Style the stats row budget stat mini. */
        .stats-row-budget .stat-mini {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        /* Style the stat mini stat label. */
        .stats-row-budget .stat-mini .stat-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            margin-bottom: 4px;
        }
        /* Style the stat mini stat value. */
        .stats-row-budget .stat-mini .stat-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1a2b3c;
        }
        /* Style the stat value blue. */
        .stats-row-budget .stat-mini .stat-value.blue { color: #c9a96e; }

        /* Style the modal overlay. */
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
        /* Style the modal overlay active. */
        .modal-overlay.active { display: flex; }
        /* Style the modal container. */
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
        /* Define the fade in scale animation. */
        @keyframes fadeInScale {
            /* Style the from. */
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            /* Style the to. */
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        /* Style the modal header. */
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        /* Style the modal header. */
        .modal-header h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: #1a2b3c;
            margin: 0;
        }
        /* Style the modal close. */
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
        /* Style the modal close on hover. */
        .modal-close:hover { color: #333; }
        /* Style the form group. */
        .form-group {
            margin-bottom: 18px;
        }
        /* Style the form group. */
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: #333;
            margin-bottom: 4px;
        }
        /* Style the form group required. */
        .form-group label .required { color: #d32f2f; }
        /* Style the form group. */
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
        /* Style the form group when focused. */
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #c9a96e;
            box-shadow: 0 0 0 3px rgba(201,169,110,0.2);
            background: #fff;
        }
        /* Style the form row. */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        /* Style the form row form group. */
        .form-row .form-group { margin-bottom: 0; }
        /* Style the modal footer. */
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 10px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        /* Style the modal footer. */
        .modal-footer button {
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: 0.3s;
            border: none;
        }
        /* Style the btn cancel. */
        .btn-cancel {
            background: transparent;
            color: #888;
        }
        /* Style the btn cancel on hover. */
        .btn-cancel:hover {
            color: #333;
            background: #f5f5f5;
        }
        /* Style the btn save. */
        .btn-save {
            background: #c9a96e;
            color: #fff;
        }
        /* Style the btn save on hover. */
        .btn-save:hover {
            background: #b8975a;
            transform: translateY(-1px);
        }
        /* Style the btn delete. */
        .btn-delete {
            background: #d32f2f;
            color: #fff;
        }
        /* Style the btn delete on hover. */
        .btn-delete:hover {
            background: #b71c1c;
            transform: translateY(-2px);
        }
        /* Style the btn edit project. */
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
        /* Style the btn edit project on hover. */
        .btn-edit-project:hover {
            background: #b8975a;
            transform: translateY(-2px);
        }

        /* Style the detail grid. */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 30px;
            padding: 20px;
            background: #faf8f5;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        /* Style the detail item. */
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        /* Style the detail item. */
        .detail-item label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            margin-bottom: 2px;
        }
        /* Style the detail value. */
        .detail-value {
            display: block;
            padding: 6px 0;
            color: #333;
            font-weight: 500;
        }
        /* Style the detail edit. */
        .detail-edit {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            background: #fafafa;
        }
        /* Style the detail edit when focused. */
        .detail-edit:focus {
            outline: none;
            border-color: #c9a96e;
            box-shadow: 0 0 0 3px rgba(201,169,110,0.2);
            background: #fff;
        }

        /* Style the budget detail grid. */
        .budget-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 30px;
            padding: 20px;
            background: #faf8f5;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        /* Style the budget detail item. */
        .budget-detail-item {
            display: flex;
            flex-direction: column;
        }
        /* Style the budget detail item. */
        .budget-detail-item label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            margin-bottom: 2px;
        }
        /* Style the budget detail value. */
        .budget-detail-value {
            display: block;
            padding: 6px 0;
            color: #333;
            font-weight: 500;
        }
        /* Style the budget detail edit. */
        .budget-detail-edit {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            background: #fafafa;
        }
        /* Style the budget detail edit when focused. */
        .budget-detail-edit:focus {
            outline: none;
            border-color: #c9a96e;
            box-shadow: 0 0 0 3px rgba(201,169,110,0.2);
            background: #fff;
        }

        /* Style the error notification. */
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
        /* Style the error notification. */
        .error-notification { background: #d32f2f; color: #fff; }
        /* Style the success notification. */
        .success-notification { background: #2e7d32; color: #fff; }
        /* Style the error notification error content. */
        .error-notification .error-content,
        .success-notification .success-content {
            display: flex;
            align-items: center;
            gap: 12px;
            justify-content: space-between;
        }
        /* Style the error notification error close. */
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
        /* Style the error notification error close on hover. */
        .error-notification .error-close:hover,
        .success-notification .success-close:hover { opacity: 1; }
        /* Define the slide down animation. */
        @keyframes slideDown {
            /* Style the from. */
            from { transform: translateX(-50%) translateY(-30px); opacity: 0; }
            /* Style the to. */
            to { transform: translateX(-50%) translateY(0); opacity: 1; }
        }

        /* Dynamic expense fields */
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
        /* Style the dynamic amount fields form group. */
        .dynamic-amount-fields .form-group {
            margin-bottom: 0;
        }
        /* Style the dynamic amount fields form group. */
        .dynamic-amount-fields .form-group label {
            font-size: 0.75rem;
            color: #666;
        }

        /* Category badge */
        .category-badge.admin {
            background: #e3f2fd;
            color: #1565c0;
        }
        /* Style the category badge direct. */
        .category-badge.direct {
            background: #e8f5e9;
            color: #2e7d32;
        }
        /* Style the category badge labor. */
        .category-badge.labor {
            background: #fff3e0;
            color: #c9a96e;
        }
        /* Style the category badge material. */
        .category-badge.material {
            background: #e6f7e6;
            color: #2e7d32;
        }
        /* Style the category badge equipment. */
        .category-badge.equipment {
            background: #e8eaf6;
            color: #3949ab;
        }
        /* Style the category badge other. */
        .category-badge.other {
            background: #f5f5f5;
            color: #666;
        }

        /* File upload */
        .file-upload-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
        }
        /* Style the file upload button. */
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
        /* Style the file upload button on hover. */
        .file-upload-button:hover {
            background: #e9edf2;
        }
        /* Style the file upload name. */
        .file-upload-name {
            color: #666;
            font-size: 0.9rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 250px;
        }
        /* Style the file upload hint. */
        .file-upload-hint {
            display: block;
            margin-top: 6px;
            color: #888;
            font-size: 0.8rem;
        }

        /* Style the expense file section. */
        .expense-file-section {
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid #e9ecef;
        }
        /* Style the expense file display. */
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
        /* Style the expense file info. */
        .expense-file-info {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        /* Style the expense file icon. */
        .expense-file-icon { font-size: 20px; flex-shrink: 0; }
        /* Style the expense file name. */
        .expense-file-name {
            color: #333;
            font-size: 0.9rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        /* Style the expense file actions. */
        .expense-file-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        /* Style the btn view file. */
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
        /* Style the btn view file on hover. */
        .btn-view-file:hover, .btn-change-file:hover { background: #f0f3f6; }
        /* Style the btn delete file. */
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
        /* Style the btn delete file on hover. */
        .btn-delete-file:hover { background: #fdeceb; }
        /* Style the file preview container. */
        .file-preview-container {
            margin-top: 12px;
            display: none;
            text-align: center;
        }
        /* Style the file preview container. */
        .file-preview-container img {
            max-width: 100%;
            max-height: 250px;
            border-radius: 8px;
            border: 1px solid #ddd;
            object-fit: contain;
        }
        /* Style the file input hidden. */
        .file-input-hidden { display: none; }

        /* Responsive */
        @media (max-width: 650px) {
            /* Style the stats row. */
            .stats-row { grid-template-columns: 1fr; max-width: 100%; }
            /* Style the stats row budget. */
            .stats-row-budget { grid-template-columns: 1fr; max-width: 100%; }
            /* Style the filter row. */
            .filter-row { flex-direction: column; align-items: stretch; }
            /* Style the filter row. */
            .filter-row select, .filter-row input { width: 100%; min-width: auto; }
            /* Style the modal container. */
            .modal-container { padding: 20px; width: 95%; }
            /* Style the detail grid. */
            .detail-grid { grid-template-columns: 1fr; gap: 12px; padding: 15px; }
            /* Style the budget detail grid. */
            .budget-detail-grid { grid-template-columns: 1fr; gap: 12px; padding: 15px; }
            /* Style the modal footer. */
            .modal-footer { flex-direction: column; align-items: stretch; }
            /* Style the modal footer. */
            .modal-footer button { width: 100%; text-align: center; }
            /* Style the form row. */
            .form-row { grid-template-columns: 1fr; }
            /* Style the error notification. */
            .error-notification, .success-notification {
                min-width: auto;
                width: 90%;
                top: 75px;
                padding: 12px 18px;
            }
            /* Style the dynamic amount fields. */
            .dynamic-amount-fields { grid-template-columns: 1fr; }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/'.$portal.'.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}?v={{ filemtime(public_path('css/ui-refresh.css')) }}">
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/modal-policy.js') }}?v={{ filemtime(public_path('js/modal-policy.js')) }}"></script>
    {{-- Set the styles used by this page section. --}}
    <style id="pfims-session-privacy">html { visibility:hidden; }</style>
    {{-- Set up the actions used by this page section. --}}
    <script data-navigation-granted="{{ session('_page_navigation_granted', false) ? '1' : '0' }}" data-session-epoch="{{ session('navigation_epoch', '') }}" src="{{ asset('js/protected-navigation.js') }}?v={{ filemtime(public_path('js/protected-navigation.js')) }}"></script>
</head>
<body class="finance-page" data-portal="{{ $portal }}" data-finance-tab="{{ $financeTab }}">

    <!-- Error notification -->
    <div id="errorNotification" class="error-notification" style="display: none;">
        <div class="error-content">
            <span class="error-icon">!</span>
            <span id="errorMessage">An error occurred. Please try again.</span>
            <button class="error-close" onclick="closeError()">×</button>
        </div>
    </div>

    <!-- Success notification -->
    <div id="successNotification" class="success-notification" style="display: none;">
        <div class="success-content">
            <span class="success-icon">●</span>
            <span id="successMessage">Saved successfully!</span>
            <button class="success-close" onclick="closeSuccess()">×</button>
        </div>
    </div>

    <!-- Delete confirmation modals -->
    <div id="deleteConfirmModal" class="modal-overlay pfims-delete-confirm" style="display: none; z-index: 9999;">
        <div class="modal-container" style="width: 400px; max-width: 95%;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="modal-close" onclick="closeDeleteModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteConfirmMessage" style="font-size:1rem;color:#333;margin-bottom:10px;">Are you sure you want to move this item to Trash Bin?</p>
                <p style="font-size:0.85rem;color:#888;margin-bottom:20px;">You can restore deleted records from Settings → Trash Bin for 30 days before permanent deletion.</p>
            </div>
            <div class="modal-footer" style="display:flex;justify-content:center;gap:12px;margin-top:10px;padding-top:20px;border-top:1px solid #e9ecef;">
                <button class="btn-cancel" onclick="closeDeleteModal()" style="padding:10px 24px;border-radius:8px;font-weight:600;font-size:0.9rem;cursor:pointer;border:none;background:transparent;color:#888;transition:0.3s;">Cancel</button>
                <button class="btn-delete" id="confirmDeleteBtn" onclick="confirmDelete()" style="padding:10px 24px;border-radius:8px;font-weight:600;font-size:0.9rem;cursor:pointer;border:none;background:#d32f2f;color:#fff;transition:0.3s;">Delete</button>
            </div>
        </div>
    </div>

    {{-- Show the delete budget confirm modal section. --}}
    <div id="deleteBudgetConfirmModal" class="modal-overlay pfims-delete-confirm" style="display: none; z-index: 9999;">
        <div class="modal-container" style="width: 400px; max-width: 95%;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="modal-close" onclick="closeBudgetDeleteModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteBudgetConfirmMessage" style="font-size:1rem;color:#333;margin-bottom:10px;">Are you sure you want to move this budget to Trash Bin?</p>
                <p style="font-size:0.85rem;color:#888;margin-bottom:20px;">You can restore deleted records from Settings → Trash Bin for 30 days before permanent deletion.</p>
            </div>
            <div class="modal-footer" style="display:flex;justify-content:center;gap:12px;margin-top:10px;padding-top:20px;border-top:1px solid #e9ecef;">
                <button class="btn-cancel" onclick="closeBudgetDeleteModal()">Cancel</button>
                <button class="btn-delete" id="confirmBudgetDeleteBtn" onclick="confirmBudgetDelete()">Delete</button>
            </div>
        </div>
    </div>

    {{-- Show the delete bond confirm modal section. --}}
    <div id="deleteBondConfirmModal" class="modal-overlay pfims-delete-confirm" style="display: none; z-index: 9999;">
        <div class="modal-container" style="width: 400px; max-width: 95%;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="modal-close" onclick="closeBondDeleteModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteBondConfirmMessage" style="font-size:1rem;color:#333;margin-bottom:10px;">Are you sure you want to move this bond to Trash Bin?</p>
                <p style="font-size:0.85rem;color:#888;margin-bottom:20px;">You can restore deleted records from Settings → Trash Bin for 30 days before permanent deletion.</p>
            </div>
            <div class="modal-footer" style="display:flex;justify-content:center;gap:12px;margin-top:10px;padding-top:20px;border-top:1px solid #e9ecef;">
                <button class="btn-cancel" onclick="closeBondDeleteModal()">Cancel</button>
                <button class="btn-delete" id="confirmBondDeleteBtn" onclick="confirmBondDelete()">Delete</button>
            </div>
        </div>
    </div>

    <!-- Full-width header -->
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
                <span>{{ auth()->user()->name === 'Administrator' ? 'Admin' : auth()->user()->name }}</span>
            </a>
        </div>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar">
        {{-- Show the page navigation. --}}
        <nav>
            <ul>
                <li>
                    <a href="{{ url('/dashboard') }}">
                        <img src="{{ asset('images/dashboard.png') }}" alt="" class="nav-link-icon" aria-hidden="true">
                        DASHBOARD
                    </a>
                </li>
                <li>
                    <a href="{{ url('/projects') }}">
                        <img src="{{ asset('images/projects.png') }}" alt="" class="nav-link-icon" aria-hidden="true">
                        PROJECTS
                    </a>
                </li>
                <li class="active">
                    <a href="{{ url('/finance') }}">
                        <img src="{{ asset('images/finance.png') }}" alt="" class="nav-link-icon" aria-hidden="true">
                        FINANCE
                    </a>
                </li>
                <li>
                    <a href="{{ url('/inventory') }}">
                        <img src="{{ asset('images/inventory.png') }}" alt="" class="nav-link-icon" aria-hidden="true">
                        INVENTORY
                    </a>
                </li>
                <li>
                    <a href="{{ url('/reports') }}">
                        <img src="{{ asset('images/folder.svg') }}" alt="" class="nav-link-icon" aria-hidden="true">
                        REPORTS
                    </a>
                </li>
            </ul>
        </nav>
        <div class="bottom-nav">
        <ul>
            <li>
                <a href="{{ url('/settings') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%; padding: 10px 16px; border-radius: 8px;">
                    <img src="{{ asset('images/settings.png') }}" alt="" class="nav-icon" style="width: 20px; height: 20px; object-fit: contain;">
                    Settings
                </a>
            </li>
            <li class="logout">
                {{-- Collect the details entered in this form. --}}
                <form method="POST" action="{{ url('/logout') }}" style="width: 100%; margin: 0; padding: 0;">
                    @csrf
                    <button type="submit" style="display: flex; align-items: center; gap: 12px; width: 100%; padding: 10px 16px; background: none; border: none; color: rgba(255, 255, 255, 0.6); font: inherit; cursor: pointer; transition: 0.2s; border-radius: 8px;">
                        <img src="{{ asset('images/logout.png') }}" alt="" class="nav-icon" style="width: 20px; height: 20px; object-fit: contain;">
                        Log out
                    </button>
                </form>
            </li>
        </ul>
    </div>
    </aside>

    <!-- Main content -->
    <main class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <h1>FINANCE</h1>
            {{-- Show the finance header actions section. --}}
            <div id="financeHeaderActions" style="display:flex;gap:10px;flex-wrap:wrap;"{{ in_array($financeTab, ['expenses', 'budgets', 'receivables', 'cash', 'backhoe', 'bonds'], true) ? '' : ' hidden' }}>
                <div class="finance-header-action-group" data-finance-tabs="expenses budgets"{{ in_array($financeTab, ['expenses', 'budgets'], true) ? '' : ' hidden' }}>
                    <button id="financeImportButton" class="btn-add-data" onclick="openPfimsImport(document.getElementById('contractsSubtab').getAttribute('aria-selected') === 'true' && currentReportTab === 'budgets' ? 'contracts' : 'finance-expenses')">Import Expenses</button>
                </div>
                <div class="finance-header-action-group" data-finance-tabs="expenses"{{ $financeTab === 'expenses' ? '' : ' hidden' }}>
                    <button class="btn-add-expense" onclick="openAddExpenseModal()">+ Add Expense</button>
                </div>
                <div class="finance-header-action-group" data-finance-tabs="budgets"{{ $financeTab === 'budgets' ? '' : ' hidden' }}>
                    <button id="addBudgetHeaderButton" class="btn-add-budget" onclick="openAddBudgetModal()">+ Add Budget</button>
                    <button id="addContractHeaderButton" class="btn-add-data gold" onclick="openAddContractModal()" hidden>+ Add Contract</button>
                </div>
                <div class="finance-header-action-group" data-finance-tabs="receivables"{{ $financeTab === 'receivables' ? '' : ' hidden' }}>
                    <button onclick="openAddReceivableModal()" class="btn-add-data gold">+ Add Entry</button>
                </div>
                <div class="finance-header-action-group" data-finance-tabs="cash"{{ $financeTab === 'cash' ? '' : ' hidden' }}>
                    <button onclick="openAddCashAccountModal()" class="btn-add-data">+ Add Account</button>
                    <button onclick="openAddCashModal()" class="btn-add-data gold">+ Add Cash Asset</button>
                </div>
                <div class="finance-header-action-group" data-finance-tabs="backhoe"{{ $financeTab === 'backhoe' ? '' : ' hidden' }}>
                    <button onclick="openAddBackhoeExpenseModal()" class="btn-add-data">+ Add Backhoe Expense</button>
                    <button onclick="openAddBackhoeRentalModal()" class="btn-add-data gold">+ Add Rental Income</button>
                </div>
                <div class="finance-header-action-group" data-finance-tabs="bonds"{{ $financeTab === 'bonds' ? '' : ' hidden' }}>
                    <button onclick="openAddBondModal()" class="btn-add-data gold">+ Add Bond</button>
                </div>
            </div>
        </div>

        <!-- Tab 1: expenses -->
        <div id="tabExpenses" class="report-section {{ $financeTab === 'expenses' ? 'active' : '' }}">
            <div class="filter-tabs">
                <span class="tab active" data-period="all" onclick="setActiveTab(this,'all')">All</span>
                <span class="tab" data-period="daily" onclick="setActiveTab(this,'daily')">Daily</span>
                <span class="tab" data-period="weekly" onclick="setActiveTab(this,'weekly')">Weekly</span>
                <span class="tab" data-period="monthly" onclick="setActiveTab(this,'monthly')">Monthly</span>
                <span class="tab" data-period="yearly" onclick="setActiveTab(this,'yearly')">Yearly</span>
            </div>
            <div class="filter-row">
                <input type="search" id="projectSearch" class="project-filter" maxlength="150" placeholder="Search project, category, description..." oninput="applyFilters()">
                <select id="projectFilter" class="project-filter" onchange="filterByProject()"><option value="all">All Projects</option></select>
                <select id="expenseScopeFilter" aria-label="Expense type" onchange="applyFilters()"><option value="all" selected>All Expenses</option><option value="direct">Direct Expenses</option><option value="admin">Admin Expenses</option><option value="office">Office Expenses</option><option value="overall">Overall Expenses</option></select>
                <select id="expenseRecordStatusFilter" aria-label="Record status" onchange="applyFilters()"><option value="all" selected>All Records</option><option value="missing_amount">Missing Amount</option><option value="no_project">No Project</option><option value="missing_amount_and_project">Missing Amount &amp; Project</option></select>
                <select id="expenseSourceFilter" aria-label="Expense source" onchange="applyFilters()"><option value="all" selected>All Sources</option><option value="inventory">From Inventory</option><option value="manual">Not From Inventory</option></select>
                <select id="expenseCategoryFilter" onchange="applyFilters()"><option value="all">All Categories</option></select>
                <button type="button" class="btn-clear-search" onclick="clearSearch()">✕ Clear Filters</button>
            </div>
            {{-- Show the expense stats section. --}}
            <div class="stats-row-budget budget-stats visible" id="expenseStats">
                {{-- Show the total expenses value section. --}}
                <div class="stat-mini"><div class="stat-label">Total Expenses</div><div class="stat-value" id="totalExpensesValue" style="color:#1a2b3c;">₱0.00</div><div class="stat-description">Recorded expenses matching the current filters.</div></div>
                {{-- Show the net variance value section. --}}
                <div class="stat-mini"><div class="stat-label">Budget Remaining</div><div class="stat-value red" id="netVarianceValue">₱0.00</div><div class="stat-description">Budget left for the filtered project expenses.</div></div>
            </div>
            <div class="table-wrapper expense-table-wrapper">
                {{-- Show the records in a table. --}}
                <table id="expenseTable" data-pfims-standard-actions="off">
                    <thead><tr><th>Project</th><th>Expense Description</th><th>Category</th><th>Component</th><th>Amount</th><th>Date</th><th>Remarks</th><th>Actions</th></tr></thead>
                    <tbody id="expenseTableBody"></tbody>
                </table>
            </div>
            {{-- Show the expense pagination section. --}}
            <div class="pagination-wrapper" id="expensePagination">
                <div class="rows-info">Rows per page
                    <select id="financeRowsPerPage" aria-label="Finance expense rows per page" onchange="changeFinancePageSize()">
                        <option value="10" selected>10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option>
                    </select>
                    <span id="rowsInfoText">Total: 0 expenses</span>
                </div>
                {{-- Show the finance pagination links section. --}}
                <div class="pagination-links" id="financePaginationLinks"></div>
            </div>
        </div>

        <!-- Tab 2: budgets -->
        <div id="tabBudgets" class="report-section {{ $financeTab === 'budgets' ? 'active' : '' }}">
            <div class="budget-contract-tabs" role="tablist" aria-label="Budgets and contracts">
                <button type="button" class="tab active" id="budgetsSubtab" role="tab" aria-selected="true" onclick="switchBudgetContractsSubtab('budgets')">Budgets</button>
                <button type="button" class="tab" id="contractsSubtab" role="tab" aria-selected="false" onclick="switchBudgetContractsSubtab('contracts')">Contracts</button>
            </div>
            {{-- Show the budgets subpanel section. --}}
            <div id="budgetsSubpanel" role="tabpanel" aria-labelledby="budgetsSubtab">
            <div class="filter-row">
                <input type="search" id="budgetSearch" maxlength="150" placeholder="Search project name..." oninput="filterBudgetTable()">
                <select id="budgetProjectFilter" onchange="filterBudgetTable()"><option value="all">All Projects</option></select>
                <select id="budgetStatusFilter" onchange="filterBudgetTable()"><option value="all">All Statuses</option><option value="On Track">On Track</option><option value="Near Limit">Near Limit</option><option value="Over Budget">Over Budget</option><option value="No Budget">No Budget</option></select>
                <button type="button" class="btn-clear-search" onclick="clearBudgetSearch()">✕ Clear Filters</button>
            </div>
            {{-- Show the budget stats section. --}}
            <div class="stats-row-budget budget-stats visible" id="budgetStats">
                {{-- Show the budget total value section. --}}
                <div class="stat-mini"><div class="stat-label">Total Budget</div><div class="stat-value blue" id="budgetTotalValue">₱0.00</div><div class="stat-description">Allocated budget for the filtered projects.</div></div>
                {{-- Show the budget remaining value section. --}}
                <div class="stat-mini"><div class="stat-label">Budget Remaining</div><div class="stat-value red" id="budgetRemainingValue">₱0.00</div><div class="stat-description">Amount still available after recorded project expenses.</div></div>
            </div>
            <div class="budget-table-wrapper visible">
                {{-- Show the records in a table. --}}
                <table id="budgetTable">
                    <thead><tr><th>Project Name</th><th>Budget Amount</th><th>Actual Spend</th><th>Remaining</th><th>Status</th></tr></thead>
                    <tbody id="budgetTableBody"><tr><td colspan="5" style="text-align:center;padding:20px;">Loading budget data...</td></tr></tbody>
                </table>
            </div>
            {{-- Show the budget pagination section. --}}
            <div class="pagination-wrapper" id="budgetPagination">
                <div class="rows-info">Rows per page
                    <select id="budgetRowsPerPage" aria-label="Finance budget rows per page" onchange="changeBudgetPageSize()">
                        <option value="10" selected>10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option>
                    </select>
                    <span id="budgetRowsInfo">Total: 0 projects</span>
                </div>
                {{-- Show the budget pagination links section. --}}
                <div class="pagination-links" id="budgetPaginationLinks"></div>
            </div>
            </div>
            {{-- Show the contracts subpanel section. --}}
            <div id="contractsSubpanel" role="tabpanel" aria-labelledby="contractsSubtab" hidden>
                <div class="filter-row">
                    <input type="search" id="profitSearch" class="project-filter" maxlength="150" placeholder="Search project or contract..." aria-label="Search contracts" oninput="filterFinanceRows('profitSearch', 'profitBody')">
                    <select id="profitYear" aria-label="Contract year" onchange="loadProfit()"><option value="">All Years</option></select>
                    <button type="button" class="btn-clear-search" onclick="clearProfitSearch()">✕ Clear Filters</button>
                </div>
                <div class="report-table-wrapper">
                    {{-- Show the records in a table. --}}
                    <table id="profitTable">
                        <thead><tr><th>Project</th><th>Start Date</th><th>End Date</th><th>Contract Price</th><th>Addl. Works</th><th>Total Contract</th><th>Original Payment</th><th>Addl. Payment</th><th>Total Payment</th><th>Project Expense</th><th>Accounts Receivable</th><th>Profit/Loss (Payment)</th><th>Profit/Loss (Contract)</th></tr></thead>
                        <tbody id="profitBody"><tr><td colspan="13" style="text-align:center;padding:20px;">Loading contracts...</td></tr></tbody>
                        <tfoot id="profitTotals"><tr class="total-row"><td><strong>TOTAL</strong></td>@for($column = 1; $column < 13; $column++)<td></td>@endfor</tr></tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 3: expovrall -->
        <div id="tabExpovrall" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Month:
                    <input type="month" id="expovrallMonth" value="{{ date('Y-m') }}" onchange="loadExpovrall()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                </label>
            </div>
            <div class="report-table-wrapper">
                {{-- Show the records in a table. --}}
                <table id="expovrallTable">
                    <thead><tr><th>Project</th><th>Const. Supply</th><th>Salaries & Wages</th><th>Permit, Taxes</th><th>Transport</th><th>Utilities</th><th>Delivery</th><th>Rent</th><th>Stationery</th><th>Depreciation</th><th>Repair & Maint</th><th>SSS/PhilHealth</th><th>Others</th><th>Total</th></tr></thead>
                    <tbody id="expovrallBody"><tr><td colspan="14" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- Tab 4: exp direct -->
        <div id="tabExpdirect" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Month:
                    <input type="month" id="expdirectMonth" value="{{ date('Y-m') }}" onchange="loadExpDirect()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                </label>
            </div>
            <div class="report-table-wrapper">
                {{-- Show the records in a table. --}}
                <table id="expdirectTable">
                    <thead><tr><th>Project</th><th>Const. Supply</th><th>Salaries & Wages</th><th>Permit, Taxes</th><th>Transport</th><th>Utilities</th><th>Delivery</th><th>Others</th><th>Total</th><th>Admin Expense</th></tr></thead>
                    <tbody id="expdirectBody"><tr><td colspan="10" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- Tab 5: admin exp -->
        <div id="tabAdminexp" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Month:
                    <input type="month" id="adminexpMonth" value="{{ date('Y-m') }}" onchange="loadAdminExp()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                </label>
            </div>
            <div class="report-table-wrapper">
                {{-- Show the records in a table. --}}
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

        <!-- Tab 6: direct exp -->
        <div id="tabDirectexp" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Month:
                    <input type="month" id="directexpMonth" value="{{ date('Y-m') }}" onchange="loadDirectExp()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                </label>
            </div>
            <div class="report-table-wrapper">
                {{-- Show the records in a table. --}}
                <table id="directexpTable">
                    <thead><tr><th>Project</th><th>Const. Supply</th><th>Salaries & Wages</th><th>Permit, Taxes</th><th>Transport</th><th>Utilities</th><th>Delivery</th><th>Others</th><th>Total</th><th>Admin Expense</th></tr></thead>
                    <tbody id="directexpBody"><tr><td colspan="10" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- Tab 7: overall exp -->
        <div id="tabOverallexp" class="report-section">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Month:
                    <input type="month" id="overallexpMonth" value="{{ date('Y-m') }}" onchange="loadOverallExp()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                </label>
            </div>
            <div class="report-table-wrapper">
                {{-- Show the records in a table. --}}
                <table id="overallexpTable">
                    <thead><tr><th>Project</th><th>Const. Supply</th><th>Salaries & Wages</th><th>Permit, Taxes</th><th>Transport</th><th>Utilities</th><th>Delivery</th><th>Others</th><th>Admin Expenses</th><th>Total</th></tr></thead>
                    <tbody id="overallexpBody"><tr><td colspan="10" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- Tab 9: ar/ap -->
        <div id="tabReceivables" class="report-section {{ $financeTab === 'receivables' ? 'active' : '' }}">
            <div class="filter-row">
                <input type="search" id="receivableSearch" class="project-filter" maxlength="150" placeholder="Search counterparty or project..." aria-label="Search accounts receivable and payable" oninput="filterFinanceRows('receivableSearch', 'receivableBody')">
                <label class="pfims-filter-field"><span>Type</span>
                <select id="receivableType" aria-label="Type" onchange="loadReceivables()">
                    <option value="accounts_receivable">Accounts Receivable</option>
                    <option value="accounts_payable">Accounts Payable</option>
                    <option value="cash_advance_site">Cash Advance (Site)</option>
                    <option value="advance_employee">Advances to Employees</option>
                </select>
                </label>
                <label class="pfims-filter-field"><span>Month</span>
                    <input type="month" id="receivableMonth" aria-label="Month" onchange="loadReceivables()">
                </label>
                <button type="button" class="btn-clear-search" onclick="clearReceivableSearch()">✕ Clear Filters</button>
            </div>
            <div class="report-table-wrapper">
                {{-- Show the records in a table. --}}
                <table id="receivableTable">
                    <thead><tr><th>Date</th><th>Counterparty Name</th><th>Project</th><th>30 Days</th><th>31-60 Days</th><th>61-90 Days</th><th>91-120 Days</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody id="receivableBody"><tr><td colspan="9" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- Tab 10: cash asset -->
        <div id="tabCash" class="report-section {{ $financeTab === 'cash' ? 'active' : '' }}">
            <div class="filter-row">
                <input type="search" id="cashSearch" placeholder="Search account..." aria-label="Search accounts" oninput="filterFinanceRows('cashSearch', 'cashBody')">
                <label class="pfims-filter-field"><span>Month</span>
                    <input type="month" id="cashMonth" aria-label="Month" onchange="loadCashAsset()">
                </label>
                <button type="button" class="btn-clear-search" onclick="document.getElementById('cashSearch').value = ''; document.getElementById('cashMonth').value = ''; loadCashAsset();">✕ Clear Filters</button>
            </div>
            <div class="report-table-wrapper">
                {{-- Show the records in a table. --}}
                <table id="cashTable">
                    <thead><tr><th>Account</th><th>Month</th><th>Balance</th></tr></thead>
                    <tbody id="cashBody"><tr><td colspan="3" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- Tab 12: backhoe -->
        <div id="tabBackhoe" class="report-section {{ $financeTab === 'backhoe' ? 'active' : '' }}">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Month:
                    <input type="month" id="backhoeMonth" value="{{ date('Y-m') }}" onchange="loadBackhoe()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                </label>
            </div>
            <div class="report-table-wrapper">
                {{-- Show the records in a table. --}}
                <table id="backhoeTable" data-pfims-standard-actions="off">
                    <thead><tr><th>Asset</th><th>Period</th><th>Project Site</th><th>Gas/Diesel</th><th>Payroll (Operator)</th><th>Repair</th><th>Other</th><th>Delivery</th><th>Transport</th><th>Total</th><th>Rental Income</th><th>Net Income</th><th>Actions</th></tr></thead>
                    <tbody id="backhoeBody"><tr><td colspan="12" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- Tab 13: bonds -->
        <div id="tabBonds" class="report-section {{ $financeTab === 'bonds' ? 'active' : '' }}">
            <div class="filter-row">
                <input type="search" id="bondSearch" class="project-filter" maxlength="150" placeholder="Search project or provider..." aria-label="Search bonds" oninput="filterFinanceRows('bondSearch', 'bondBody')">
                <select id="bondProjectFilter" aria-label="Bond project" onchange="loadBonds()">
                    <option value="all">All Projects</option>
                </select>
                <select id="bondStatusFilter" aria-label="Bond status" onchange="loadBonds()">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="released">Released</option>
                    <option value="forfeited">Forfeited</option>
                </select>
                <label class="pfims-filter-field"><span>Month</span><input type="month" id="bondMonth" aria-label="Month" onchange="loadBonds()"></label>
                <button type="button" class="btn-clear-search" onclick="clearBondSearch()">✕ Clear Filters</button>
            </div>
            <div class="report-table-wrapper">
                {{-- Show the records in a table. --}}
                <table id="bondTable">
                    <thead><tr><th>Project</th><th>Date</th><th>Provider</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody id="bondBody"><tr><td colspan="6" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- Tab 14: summary -->
        <div id="tabSummary" class="report-section {{ $financeTab === 'summary' ? 'active' : '' }}">
            <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;">Month:
                    <input type="month" id="summaryMonth" value="{{ date('Y-m') }}" onchange="loadSummary()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">
                </label>
            </div>
            <div class="report-table-wrapper">
                {{-- Show the records in a table. --}}
                <table id="summaryTable">
                    <thead><tr><th>Category</th><th>Amount</th></tr></thead>
                    <tbody id="summaryBody"><tr><td colspan="2" style="text-align:center;padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

    </main>

    {{-- Show the inventory expense modal section. --}}
    <div id="inventoryExpenseModal" class="modal-overlay pfims-add-modal">
        <div class="modal-container">
            <div class="modal-header"><h2>Edit Stock-In Expense</h2><button class="modal-close" onclick="closeInventoryExpenseModal()">×</button></div>
            <div class="modal-body">
                {{-- Show the inventory expense amount section. --}}
                <div class="form-group"><label>Amount <span class="required">*</span></label><input type="number" id="inventoryExpenseAmount" min="0.01" step="0.01" placeholder="0.00"></div>
            </div>
            {{-- Show the inventory expense save btn section. --}}
            <div class="modal-footer"><button class="btn-cancel" onclick="closeInventoryExpenseModal()">Cancel</button><button class="btn-save" id="inventoryExpenseSaveBtn" onclick="saveInventoryExpense()">Save Changes</button></div>
        </div>
    </div>

    <!-- Add expense modal (unified - handles all expense types) -->
    <div id="addExpenseModal" class="modal-overlay pfims-add-modal">
        <div class="modal-container">
            {{-- Show the add expense modal title section. --}}
            <div class="modal-header"><h2 id="addExpenseModalTitle">Add Expense</h2><button class="modal-close" onclick="closeAddExpenseModal()">×</button></div>
            <div class="modal-body">
                {{-- Show the expense type section. --}}
                <div class="form-group"><label>Expense Type <span class="required">*</span></label><select id="expenseType" onchange="updateExpenseType()"><option value="direct">Direct</option><option value="admin">Admin</option></select></div>
                <div class="form-group"><label>Category <span class="required">*</span></label>
                    <select id="expenseCategory" onchange="toggleExpenseAmountFields()">
                        <option value="">Select Category...</option>
                    </select>
                </div>
                {{-- Show the expense project group section. --}}
                <div class="form-group" id="expenseProjectGroup"><label>Project Name <span class="required">*</span></label><select id="expenseProject"><option value="">Select Project...</option></select></div>
                {{-- Show the single amount field section. --}}
                <div class="form-group" id="singleAmountField">
                    <label>Amount <span class="required">*</span></label>
                    <input type="number" min="0.01" step="0.01" placeholder="0.00" id="expenseAmount">
                </div>

                {{-- Show the expense date section. --}}
                <div class="form-group"><label>Date <span class="required">*</span></label><input type="date" id="expenseDate" value="{{ date('Y-m-d') }}" max="{{ today()->toDateString() }}"></div>
                {{-- Show the expense desc section. --}}
                <div class="form-group"><label>Expense Description <span class="required">*</span></label><input type="text" placeholder="Describe the expense" id="expenseDesc"></div>
                {{-- Show the expense remarks section. --}}
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

    <!-- Add budget modal -->
    <div id="addBudgetModal" class="modal-overlay pfims-add-modal">
        <div class="modal-container">
            <div class="modal-header"><h2>Add Budget</h2><button class="modal-close" onclick="closeAddBudgetModal()">×</button></div>
            <div class="modal-body">
                {{-- Show the budget project section. --}}
                <div class="form-group"><label>Project <span class="required">*</span></label><select id="budgetProject"><option value="">Select Project...</option></select></div>
                {{-- Show the budget amount section. --}}
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

    <!-- Add contract modal -->
    <div id="addContractModal" class="modal-overlay pfims-add-modal">
        <div class="modal-container">
            {{-- Show the contract modal title section. --}}
            <div class="modal-header"><h2 id="contractModalTitle">Add Contract</h2><button class="modal-close" onclick="closeAddContractModal()">×</button></div>
            <div class="modal-body">
                {{-- Show the contract project section. --}}
                <div class="form-group"><label>Project <span class="required">*</span></label><select id="contractProject" onchange="updateContractBudgetDisplay()"><option value="">Select Project...</option></select><span id="contractProjectDisplay" style="display:none;"></span></div>
                {{-- Show the contract budget display section. --}}
                <div class="form-group"><label>Contract Price</label><strong id="contractBudgetDisplay">₱0.00</strong></div>
                {{-- Show the contract addl works section. --}}
                <div class="form-group"><label>Additional Works Contract</label><input id="contractAddlWorks" type="number" min="0" step="0.01" value="0"></div>
                {{-- Show the contract payment section. --}}
                <div class="form-group"><label>Original Payment Received</label><input id="contractPayment" type="number" min="0" step="0.01" value="0"></div>
                {{-- Show the contract addl payment section. --}}
                <div class="form-group"><label>Additional Works Payment</label><input id="contractAddlPayment" type="number" min="0" step="0.01" value="0"></div>
                {{-- Show the contract remarks section. --}}
                <div class="form-group"><label>Remarks</label><input id="contractRemarks" type="text" maxlength="255"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddContractModal()">Cancel</button>
                <button id="contractDeleteBtn" class="btn-delete" style="display:none;" onclick="deleteContract()">Delete</button>
                <button id="contractEditBtn" class="btn-edit-project" style="display:none;" onclick="enableContractEdit()">Edit</button>
                <button id="contractSaveBtn" class="btn-save" onclick="saveContract()">Save Contract</button>
            </div>
        </div>
    </div>

    <!-- Add receivable modal -->
    <div id="addReceivableModal" class="modal-overlay pfims-add-modal">
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
                {{-- Show the receivable counterparty section. --}}
                <div class="form-group"><label>Counterparty Name <span class="required">*</span></label><input type="text" placeholder="Client/Vendor/Employee name" id="receivableCounterparty"></div>
                {{-- Show the receivable project section. --}}
                <div class="form-group"><label>Project</label><select id="receivableProject"><option value="">Select Project...</option></select></div>
                {{-- Show the receivable date section. --}}
                <div class="form-group"><label>Date <span class="required">*</span></label><input type="date" id="receivableDate" value="{{ date('Y-m-d') }}"></div>
                <div class="form-row">
                    {{-- Show the receivable30d section. --}}
                    <div class="form-group"><label>30 Days</label><input type="number" step="0.01" placeholder="0.00" id="receivable30d"></div>
                    {{-- Show the receivable60d section. --}}
                    <div class="form-group"><label>31-60 Days</label><input type="number" step="0.01" placeholder="0.00" id="receivable60d"></div>
                </div>
                <div class="form-row">
                    {{-- Show the receivable90d section. --}}
                    <div class="form-group"><label>61-90 Days</label><input type="number" step="0.01" placeholder="0.00" id="receivable90d"></div>
                    {{-- Show the receivable120d section. --}}
                    <div class="form-group"><label>91-120 Days</label><input type="number" step="0.01" placeholder="0.00" id="receivable120d"></div>
                </div>
                <div class="form-group"><label>Status</label>
                    <select id="receivableStatus">
                        <option value="outstanding">Outstanding</option>
                        <option value="settled">Settled</option>
                    </select>
                </div>
                {{-- Show the receivable remarks section. --}}
                <div class="form-group"><label>Remarks</label><input type="text" placeholder="Additional notes..." id="receivableRemarks"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddReceivableModal()">Cancel</button>
                <button class="btn-save" onclick="saveReceivable()">Add Entry</button>
            </div>
        </div>
    </div>

    <!-- Add cash asset modal -->
    <div id="addCashAccountModal" class="modal-overlay pfims-add-modal">
        <div class="modal-container">
            <div class="modal-header"><h2>Add Account</h2><button class="modal-close" onclick="closeAddCashAccountModal()">×</button></div>
            <div class="modal-body">
                <div class="form-group"><label for="cashAccountTitle">Account Title <span class="required">*</span></label>
                    <input type="text" id="cashAccountTitle" maxlength="100" placeholder="Enter account title" required>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddCashAccountModal()">Cancel</button>
                <button class="btn-save" id="saveCashAccountButton" data-pfims-no-expand onclick="saveCashAccount()">Add Account</button>
            </div>
        </div>
    </div>

    {{-- Show the add cash modal section. --}}
    <div id="addCashModal" class="modal-overlay pfims-add-modal">
        <div class="modal-container">
            <div class="modal-header"><h2>Add Cash Asset</h2><button class="modal-close" onclick="closeAddCashModal()">×</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Account <span class="required">*</span></label>
                    <select id="cashAccount">
                        <option value="">Select Account...</option>
                    </select>
                    <div class="cash-account-actions">
                        <button type="button" class="btn-edit-project" onclick="editSelectedCashAccount()">Rename Account</button>
                        <button type="button" class="btn-delete" onclick="deleteSelectedCashAccount()">Delete Account</button>
                    </div>
                </div>
                {{-- Show the cash period section. --}}
                <div class="form-group"><label>Month <span class="required">*</span></label><input type="month" id="cashPeriod" value="{{ date('Y-m') }}" max="{{ today()->format('Y-m') }}"></div>
                {{-- Show the cash balance section. --}}
                <div class="form-group"><label>Balance <span class="required">*</span></label><input type="number" step="0.01" placeholder="0.00" id="cashBalance"></div>
                {{-- Show the cash remarks section. --}}
                <div class="form-group"><label>Remarks</label><input type="text" placeholder="Additional notes..." id="cashRemarks"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddCashModal()">Cancel</button>
                <button class="btn-save" onclick="saveCashPosition()">Add Cash Asset</button>
            </div>
        </div>
    </div>

    <!-- Add repair modal -->
    <div id="addRepairModal" class="modal-overlay pfims-add-modal">
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
                {{-- Show the repair amount section. --}}
                <div class="form-group"><label>Amount <span class="required">*</span></label><input type="number" step="0.01" placeholder="0.00" id="repairAmount"></div>
                {{-- Show the repair date section. --}}
                <div class="form-group"><label>Date <span class="required">*</span></label><input type="date" id="repairDate" value="{{ date('Y-m-d') }}" max="{{ today()->toDateString() }}"></div>
                {{-- Show the repair remarks section. --}}
                <div class="form-group"><label>Remarks</label><input type="text" placeholder="Additional notes..." id="repairRemarks"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddRepairModal()">Cancel</button>
                <button class="btn-save" onclick="saveRepair()">Add Repair</button>
            </div>
        </div>
    </div>

    <!-- Add backhoe expense modal -->
    <div id="addBackhoeExpenseModal" class="modal-overlay pfims-add-modal">
        <div class="modal-container">
            {{-- Show the backhoe expense modal title section. --}}
            <div class="modal-header"><h2 id="backhoeExpenseModalTitle">Add Backhoe Expense</h2><button class="modal-close" onclick="closeAddBackhoeExpenseModal()">×</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Asset <span class="required">*</span></label>
                    <select id="backhoeExpenseAsset">
                        <option value="">Select Asset...</option>
                    </select>
                </div>
                {{-- Show the backhoe expense project section. --}}
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
                {{-- Show the backhoe expense amount section. --}}
                <div class="form-group"><label>Amount <span class="required">*</span></label><input type="number" step="0.01" placeholder="0.00" id="backhoeExpenseAmount"></div>
                {{-- Show the backhoe expense date section. --}}
                <div class="form-group"><label>Date <span class="required">*</span></label><input type="date" id="backhoeExpenseDate" value="{{ date('Y-m-d') }}" max="{{ today()->toDateString() }}"></div>
                {{-- Show the backhoe expense remarks section. --}}
                <div class="form-group"><label>Remarks</label><input type="text" placeholder="Additional notes..." id="backhoeExpenseRemarks"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddBackhoeExpenseModal()">Cancel</button>
                <button class="btn-save" id="backhoeExpenseSaveBtn" onclick="saveBackhoeExpense()">Add Backhoe Expense</button>
            </div>
        </div>
    </div>

    <!-- Add backhoe rental modal -->
    <div id="addBackhoeRentalModal" class="modal-overlay pfims-add-modal">
        <div class="modal-container">
            {{-- Show the backhoe rental modal title section. --}}
            <div class="modal-header"><h2 id="backhoeRentalModalTitle">Add Backhoe Rental Income</h2><button class="modal-close" onclick="closeAddBackhoeRentalModal()">×</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Asset <span class="required">*</span></label>
                    <select id="backhoeRentalAsset">
                        <option value="">Select Asset...</option>
                    </select>
                </div>
                {{-- Show the backhoe rental project section. --}}
                <div class="form-group"><label>Project Site</label><select id="backhoeRentalProject"><option value="">Select Project...</option></select></div>
                {{-- Show the backhoe rental period section. --}}
                <div class="form-group"><label>Period <span class="required">*</span></label><input type="month" id="backhoeRentalPeriod" value="{{ date('Y-m') }}" max="{{ today()->format('Y-m') }}"></div>
                {{-- Show the backhoe rental amount section. --}}
                <div class="form-group"><label>Amount <span class="required">*</span></label><input type="number" step="0.01" placeholder="0.00" id="backhoeRentalAmount"></div>
                {{-- Show the backhoe rental remarks section. --}}
                <div class="form-group"><label>Remarks</label><input type="text" placeholder="Additional notes..." id="backhoeRentalRemarks"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddBackhoeRentalModal()">Cancel</button>
                <button class="btn-save" id="backhoeRentalSaveBtn" onclick="saveBackhoeRental()">Add Rental Income</button>
            </div>
        </div>
    </div>

    <!-- Equipment record details -->
    <div id="equipmentDetailModal" class="modal-overlay finance-edit-modal">
        <div class="modal-container">
            <div class="modal-header"><h2>Equipment Details</h2><button class="modal-close" onclick="closeEquipmentDetailModal()">×</button></div>
            <div class="modal-body">
                {{-- Show the equipment detail summary section. --}}
                <div id="equipmentDetailSummary" class="detail-grid"></div>
                <h3 style="margin:18px 0 10px;">Underlying records</h3>
                {{-- Show the equipment detail body section. --}}
                <div class="report-table-wrapper"><table data-pfims-standard-actions="off"><thead><tr><th>Type</th><th>Expense Date / Rental Period</th><th>Project</th><th>Amount</th><th>Remarks</th><th>Actions</th></tr></thead><tbody id="equipmentDetailBody"></tbody></table></div>
            </div>
            <div class="modal-footer"><button class="btn-cancel" onclick="closeEquipmentDetailModal()">Cancel</button><button class="btn-edit-project" onclick="openEquipmentPeriodEdit()">Edit</button><button class="btn-delete" onclick="deleteEquipmentPeriod()">Delete</button></div>
        </div>
    </div>

    {{-- Show the equipment period edit modal section. --}}
    <div id="equipmentPeriodEditModal" class="modal-overlay finance-edit-modal"><div class="modal-container">
    <div class="modal-header"><h2>Edit Equipment Asset and Period</h2><button class="modal-close" onclick="closeEquipmentPeriodEdit()">×</button></div>
    {{-- Show the equipment period asset section. --}}
    <div class="modal-body"><div class="form-group"><label>Asset *</label><select id="equipmentPeriodAsset"></select></div><div class="form-group"><label>Period *</label><input type="month" id="equipmentPeriodMonth" max="{{ today()->format('Y-m') }}"></div></div>
    <div class="modal-footer"><button class="btn-cancel" onclick="closeEquipmentPeriodEdit()">Cancel</button><button class="btn-save" onclick="saveEquipmentPeriod()">Save Changes</button></div></div></div>
    <!-- Add bond modal -->
    <div id="addBondModal" class="modal-overlay pfims-add-modal">
        <div class="modal-container">
            <div class="modal-header"><h2>Add Construction Bond</h2><button class="modal-close" onclick="closeAddBondModal()">×</button></div>
            <div class="modal-body">
                {{-- Show the bond project section. --}}
                <div class="form-group"><label>Project <span class="required">*</span></label><select id="bondProject"><option value="">Select Project...</option></select></div>
                {{-- Show the bond date section. --}}
                <div class="form-group"><label>Date <span class="required">*</span></label><input type="date" id="bondDate" value="{{ date('Y-m-d') }}" max="{{ today()->toDateString() }}"></div>
                {{-- Show the bond amount section. --}}
                <div class="form-group"><label>Amount <span class="required">*</span></label><input type="number" step="0.01" placeholder="0.00" id="bondAmount"></div>
                {{-- Show the bond provider section. --}}
                <div class="form-group"><label>Provider</label><input type="text" placeholder="Bond provider..." id="bondProvider"></div>
                {{-- Show the bond status section. --}}
                <div class="form-group"><label>Status</label><select id="bondStatus"><option value="active">Active</option><option value="released">Released</option><option value="forfeited">Forfeited</option></select></div>
                {{-- Show the bond remarks section. --}}
                <div class="form-group"><label>Remarks</label><input type="text" placeholder="Additional notes..." id="bondRemarks"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddBondModal()">Cancel</button>
                <button class="btn-save" onclick="saveBond()">Add Bond</button>
            </div>
        </div>
    </div>

    <!-- Expense detail modal -->
    <div id="expenseDetailModal" class="modal-overlay modal-update finance-edit-modal">
        <div class="modal-container">
            {{-- Show the detail modal title section. --}}
            <div class="modal-header"><div><h2 id="detailModalTitle">Expense Details</h2></div><button class="modal-close" onclick="closeExpenseDetailModal()">×</button></div>
            <div class="detail-grid">
                {{-- Show the detail expense type display section. --}}
                <div class="detail-item"><label>Expense Type</label><span id="detailExpenseTypeDisplay" class="detail-value">—</span><select id="detailExpenseTypeEdit" class="detail-edit" style="display:none;" onchange="updateDetailExpenseType()"><option value="direct">Direct Expenses</option><option value="admin">Admin Expenses</option><option value="office">Office Expenses</option><option value="overall">Overall Expenses</option></select></div>
                {{-- Show the detail project group section. --}}
                <div class="detail-item" id="detailProjectGroup"><label>Project</label><span id="detailProjectDisplay" class="detail-value">—</span><select id="detailProjectEdit" class="detail-edit" style="display:none;"></select></div>
                {{-- Show the detail desc display section. --}}
                <div class="detail-item"><label>Expense Description</label><span id="detailDescDisplay" class="detail-value">—</span><input type="text" id="detailDescEdit" class="detail-edit" style="display:none;"></div>
                {{-- Show the detail category display section. --}}
                <div class="detail-item"><label>Category</label><span id="detailCategoryDisplay" class="detail-value">—</span><select id="detailCategoryEdit" class="detail-edit" style="display:none;" onchange="updateDetailExpenseCategory()"></select></div>
                {{-- Show the detail component group section. --}}
                <div class="detail-item" id="detailComponentGroup"><label>Project Cost Component</label><span id="detailCostComponentDisplay" class="detail-value">—</span></div>
                {{-- Show the detail amount display section. --}}
                <div class="detail-item"><label>Amount</label><span id="detailAmountDisplay" class="detail-value">—</span><input type="number" step="0.01" id="detailAmountEdit" class="detail-edit" style="display:none;"></div>
                {{-- Show the detail date display section. --}}
                <div class="detail-item"><label>Date</label><span id="detailDateDisplay" class="detail-value">—</span><input type="date" id="detailDateEdit" class="detail-edit" max="{{ today()->toDateString() }}" style="display:none;"></div>
                {{-- Show the detail remarks display section. --}}
                <div class="detail-item"><label>Remarks</label><span id="detailRemarksDisplay" class="detail-value">—</span><input type="text" id="detailRemarksEdit" class="detail-edit" style="display:none;"></div>
                <div class="expense-file-section">
                    <label>Supporting File</label>
                    {{-- Show the expense file display section. --}}
                    <div id="expenseFileDisplay" class="expense-file-display">
                        <div class="expense-file-info">
                            <span id="expenseFileIcon" class="expense-file-icon">File</span>
                            <span id="expenseFileNameDisplay" class="expense-file-name">No file attached</span>
                        </div>
                        <div class="expense-file-actions">
                            <button type="button" id="viewExpenseFileBtn" class="btn-view-file" style="display: none;" onclick="viewExpenseFile()">View File</button>
                            <label for="detailExpenseFile" id="changeExpenseFileBtn" class="btn-change-file" style="display: none;">Change File</label>
                            <button type="button" id="deleteExpenseFileBtn" class="btn-delete-file" style="display: none;" onclick="deleteExpenseFile()">Delete File</button>
                        </div>
                    </div>
                    <input type="file" id="detailExpenseFile" class="file-input-hidden" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" onchange="handleDetailFileSelect(this)">
                    {{-- Show the file preview container section. --}}
                    <div id="filePreviewContainer" class="file-preview-container">
                        <img id="filePreviewImage" alt="File Preview">
                    </div>
                </div>
            </div>
            <div class="modal-footer expense-detail-footer">
                <button class="btn-cancel" onclick="closeExpenseDetailModal()">Cancel</button>
                <div class="expense-detail-footer-actions">
                    <button class="btn-delete" id="detailDeleteBtn" onclick="deleteExpense()">Delete</button>
                    <button type="button" class="btn-edit-project" id="detailViewInventoryBtn" style="display:none;" onclick="openLinkedInventoryTransaction(event)">View Inventory</button>
                    <button class="btn-edit-project" id="detailEditBtn" onclick="toggleDetailEdit()">Edit</button>
                    <button class="btn-save" id="detailSaveBtn" style="display:none;" onclick="saveDetailChanges()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Budget detail modal -->
    <div id="budgetDetailModal" class="modal-overlay modal-update finance-edit-modal">
        <div class="modal-container">
            {{-- Show the budget detail modal title section. --}}
            <div class="modal-header"><div><h2 id="budgetDetailModalTitle">Budget Details</h2></div><button class="modal-close" onclick="closeBudgetDetailModal()">×</button></div>
            <div class="budget-detail-grid">
                {{-- Show the budget detail project display section. --}}
                <div class="budget-detail-item"><label for="budgetDetailProjectEdit">Project</label><span id="budgetDetailProjectDisplay" class="budget-detail-value">—</span><select id="budgetDetailProjectEdit" class="budget-detail-edit" style="display:none;" required><option value="">Select Project...</option></select></div>
                {{-- Show the budget detail amount display section. --}}
                <div class="budget-detail-item"><label>Budget Amount</label><span id="budgetDetailAmountDisplay" class="budget-detail-value">—</span><input type="number" step="0.01" id="budgetDetailAmountEdit" class="budget-detail-edit" style="display:none;"></div>
                {{-- Show the budget detail actual display section. --}}
                <div class="budget-detail-item"><label>Actual Spend</label><span id="budgetDetailActualDisplay" class="budget-detail-value">—</span></div>
                {{-- Show the budget detail remaining display section. --}}
                <div class="budget-detail-item"><label>Remaining</label><span id="budgetDetailRemainingDisplay" class="budget-detail-value">—</span></div>
                <div class="expense-file-section">
                    <label>Supporting File</label>
                    {{-- Show the budget file display section. --}}
                    <div id="budgetFileDisplay" class="expense-file-display">
                        <div class="expense-file-info">
                            <span id="budgetFileIcon" class="expense-file-icon">File</span>
                            <span id="budgetFileNameDisplay" class="expense-file-name">No file attached</span>
                        </div>
                        <div class="expense-file-actions">
                            <button type="button" id="viewBudgetFileBtn" class="btn-view-file" style="display: none;" onclick="viewBudgetFile()">View File</button>
                            <label for="detailBudgetFile" id="changeBudgetFileBtn" class="btn-change-file" style="display: none;">Change File</label>
                            <button type="button" id="deleteBudgetFileBtn" class="btn-delete-file" style="display: none;" onclick="deleteBudgetFile()">Delete File</button>
                        </div>
                    </div>
                    <input type="file" id="detailBudgetFile" class="file-input-hidden" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" onchange="handleBudgetDetailFileSelect(this)">
                    {{-- Show the budget file preview container section. --}}
                    <div id="budgetFilePreviewContainer" class="file-preview-container">
                        <img id="budgetFilePreviewImage" alt="File Preview">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeBudgetDetailModal()">Cancel</button>
                <button class="btn-delete" id="budgetDetailDeleteBtn" onclick="deleteBudget()">Delete</button>
                <button class="btn-edit-project" id="budgetDetailEditBtn" onclick="toggleBudgetDetailEdit()">Edit</button>
                <button class="btn-save" id="budgetDetailSaveBtn" style="display:none;" onclick="saveBudgetDetailChanges()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Receivable detail modal -->
    <div id="receivableDetailModal" class="modal-overlay modal-update finance-edit-modal">
        <div class="modal-container">
            {{-- Show the receivable modal title section. --}}
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
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeReceivableModal()">Cancel</button>
                <button class="btn-delete" id="receivableDetailDeleteBtn" onclick="deleteReceivable()">Delete</button>
                <button class="btn-edit-project" id="receivableDetailEditBtn" onclick="toggleReceivableEdit()">Edit</button>
                <button class="btn-save" id="receivableDetailSaveBtn" style="display:none;" onclick="saveReceivableDetail()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Bond detail modal -->
    <div id="bondDetailModal" class="modal-overlay modal-update finance-edit-modal">
        <div class="modal-container">
            {{-- Show the bond modal title section. --}}
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
                    <input type="date" id="bondDetailDateEdit" class="detail-edit" max="{{ today()->toDateString() }}" style="display:none;">
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
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeBondModal()">Cancel</button>
                <button class="btn-delete" id="bondDetailDeleteBtn" onclick="deleteBond()">Delete</button>
                <button class="btn-edit-project" id="bondDetailEditBtn" onclick="toggleBondEdit()">Edit</button>
                <button class="btn-save" id="bondDetailSaveBtn" style="display:none;" onclick="saveBondDetail()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Cash asset detail modal -->
    <div id="cashDetailModal" class="modal-overlay modal-update finance-edit-modal">
        <div class="modal-container">
            {{-- Show the cash modal title section. --}}
            <div class="modal-header"><div><h2 id="cashModalTitle">Cash Asset Details</h2></div><button class="modal-close" onclick="closeCashModal()">×</button></div>
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
                    <input type="month" id="cashDetailPeriodEdit" class="detail-edit" max="{{ today()->format('Y-m') }}" style="display:none;">
                </div>
                <div class="detail-item">
                    <label>Balance</label>
                    <span id="cashDetailBalance" class="detail-value">—</span>
                    <input type="number" step="0.01" id="cashDetailBalanceEdit" class="detail-edit" style="display:none;">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeCashModal()">Cancel</button>
                <button class="btn-delete" id="cashDetailDeleteBtn" onclick="deleteCashPosition()">Delete</button>
                <button class="btn-edit-project" id="cashDetailEditBtn" onclick="toggleCashEdit()">Edit</button>
                <button class="btn-save" id="cashDetailSaveBtn" style="display:none;" onclick="saveCashDetail()">Save Changes</button>
            </div>
        </div>
    </div>

    {{-- Set up the actions used by this page section. --}}
    <script>
        // State variables
        var financeProjects = [];
        var financeCategories = [];
        var financeExpenses = [];
        var financeFilteredData = [];
        var financePageSize = 10;
        var financeCurrentPage = 1;
        var pendingInventoryTransactionId = null;
        var currentDetailRow = null;
        var isEditMode = false;
        var currentReportTab = 'expenses';

        var budgetData = [];
        var budgetFilteredData = [];
        var budgetPageSize = 10;
        var budgetCurrentPage = 1;
        var budgetProjectFilter = 'all';
        var budgetSearchTerm = '';
        var currentBudgetRow = null;
        var isBudgetEditMode = false;

        // Change this: default to 'all' instead of 'monthly'
        var currentPeriod = 'all';
        var currentSearchTerm = '';
        var currentProjectFilter = 'all';

        var deleteCallback = null;
        var budgetDeleteCallback = null;
        var bondDeleteCallback = null;
        var errorTimeout = null;
        var successTimeout = null;

        var API_BASE = '/api';
        var assets = [];
        var cashAccounts = [];
        var equipmentExpenseRecords = [];
        var equipmentRentalRecords = [];
        var equipmentRecordsByPeriod = {};
        var currentEquipmentPeriodKey = null;
        var equipmentEditState = { type: null, id: null };

        // Admin category codes
        var PROJECT_COST_COMPONENT_LABELS = {
            material: 'Material',
            labor: 'Labor',
            equipment: 'Equipment',
            other: 'Other'
        };

        // File upload ui state
        var selectedDetailFile = null;
        var currentExpenseFileUrl = null;
        var expenseFileMarkedForRemoval = false;
        var selectedBudgetDetailFile = null;
        var currentBudgetFileUrl = null;
        var budgetFileMarkedForRemoval = false;

        // Category mappings
        var EXPOVRALL_CATEGORIES = ['CONST_SUPPLY', 'SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'RENT', 'STATIONERY', 'DEPRECIATION', 'REPAIR_MAINT', 'SSS_PHILHEALTH', 'OTHERS'];
        var EXP_DIRECT_CATEGORIES = ['CONST_SUPPLY', 'SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'OTHERS'];
        var ADMIN_EXP_CATEGORIES = ['SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'RENT', 'STATIONERY', 'DEPRECIATION', 'REPAIR_MAINT', 'MISC', 'PENALTY', 'SSS_PHILHEALTH', 'OTHERS'];

        // Utility functions
        function hideBadge(event) { var badge = document.getElementById('notifBadge'); if (badge) badge.style.display = 'none'; }

        // Show error.
        function showError(message) {
            // Run this step if the show pfims alert equals function.
            if (typeof window.showPfimsAlert === 'function') {
                closeError();
                return window.showPfimsAlert(message || 'An error occurred. Please try again.', 'error');
            }
            var notif = document.getElementById('errorNotification');
            var msgSpan = document.getElementById('errorMessage');
            // Run this step if the msg span is set.
            if (msgSpan) msgSpan.textContent = message || 'An error occurred. Please try again.';
            notif.style.display = 'block';
            // Run this step if the error timeout is set.
            if (errorTimeout) clearTimeout(errorTimeout);
            errorTimeout = setTimeout(closeError, 5000);
        }

        // Close error.
        function closeError() {
            document.getElementById('errorNotification').style.display = 'none';
            // Run this step if the error timeout is set.
            if (errorTimeout) { clearTimeout(errorTimeout); errorTimeout = null; }
        }

        // Show success.
        function showSuccess(message) {
            var notif = document.getElementById('successNotification');
            var msgSpan = document.getElementById('successMessage');
            // Run this step if the msg span is set.
            if (msgSpan) msgSpan.textContent = message || 'Saved successfully!';
            notif.style.display = 'block';
            // Run this step if the success timeout is set.
            if (successTimeout) clearTimeout(successTimeout);
            successTimeout = setTimeout(closeSuccess, 5000);
        }

        // Close success.
        function closeSuccess() {
            document.getElementById('successNotification').style.display = 'none';
            // Run this step if the success timeout is set.
            if (successTimeout) { clearTimeout(successTimeout); successTimeout = null; }
        }

        // Show the amount as money.
        function formatCurrency(value) {
            var amount = parseFloat(value) || 0;
            return '₱' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Check the admin category condition.
        function isAdminCategory(categoryCode) {
            // Run the find action.
            var category = financeCategories.find(function(item) { return item.category_code === categoryCode; });
            return String(category && category.classification || '').toLowerCase() === 'admin';
        }

        // Check the office expense condition.
        function isOfficeExpense(expense) {
            // Return early if the is office expense equals true.
            if (expense.is_office_expense === true) return true;
            var categoryId = String(expense.fin_category_id || expense.expense_category_id || '');
            // Run the find action.
            var category = financeCategories.find(function(item) {
                return String(item.fin_category_id || item.expense_category_id || '') === categoryId;
            });
            return String(category && category.classification || '').toLowerCase() === 'office';
        }

        // Get the expense type for category.
        function expenseTypeForCategory(category) {
            return String(category && category.classification || '').toLowerCase();
        }

        // Filter expense category select.
        function filterExpenseCategorySelect(selectId, type, selectedId) {
            var select = document.getElementById(selectId);
            // Return early if the select is missing or false.
            if (!select) return;
            select.innerHTML = '<option value="">Select Category...</option>';
            var addCategoryOrder = {
                direct: ['CONSTRUCTION_SUPPLY', 'SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPORTATION_EXPENSES', 'UTILITIES_WATER_POWER', 'DELIVERY_EXPENSE', 'OTHERS_SOS_ETC'],
                admin: ['ADMIN_SALARIES_WAGES', 'ADMIN_PERMIT_TAXES_LICENSES', 'ADMIN_TRANSPORTATION_EXPENSES', 'ADMIN_UTILITIES_WATER_POWER', 'ADMIN_DELIVERY_EXPENSE', 'RENT_EXPENSE', 'STATIONARY_EXPENSE', 'DEPRECIATION_EXPENSE', 'REPAIR_MAINTENANCE', 'MISCELLANEOUS_EXPENSE', 'PENALTY_EXPENSE', 'SSS_PHILHEALTH', 'OTHERS']
            };
            // Keep the matching category items.
            var categories = financeCategories.filter(function(category) {
                return type === 'overall' || expenseTypeForCategory(category) === type;
            });
            // Run this step if the select id equals expenseCategory.
            if (selectId === 'expenseCategory') {
                var order = addCategoryOrder[type] || [];
                // Keep the matching category items.
                categories = categories.filter(function(category) { return order.includes(category.category_code); });
                // Run the sort action.
                categories.sort(function(a, b) { return order.indexOf(a.category_code) - order.indexOf(b.category_code); });
            }
            // Process each category.
            categories.forEach(function(category) {
                var option = document.createElement('option');
                option.value = category.fin_category_id || category.expense_category_id;
                option.dataset.code = category.category_code || '';
                option.dataset.classification = category.classification || '';
                option.textContent = category.category_name || category.category_code;
                select.appendChild(option);
            });
            // Run this step if the selected id is set.
            if (selectedId) select.value = selectedId;
        }

        // Update expense type.
        function updateExpenseType() {
            var type = document.getElementById('expenseType').value;
            filterExpenseCategorySelect('expenseCategory', type);
            var projectSelect = document.getElementById('expenseProject');
            projectSelect.innerHTML = '<option value="">Select Project...</option>';
            projectSelect.add(new Option('Office', 'office'));
            // Run this step if the type equals direct.
            if (type === 'direct') {
                // Process each project.
                financeProjects.forEach(function(project) {
                    projectSelect.add(new Option(project.project_name, project.project_id));
                });
            }
            toggleExpenseAmountFields();
        }

        // Update detail expense type.
        function updateDetailExpenseType(selectedId) {
            var type = document.getElementById('detailExpenseTypeEdit').value;
            filterExpenseCategorySelect('detailCategoryEdit', type, selectedId);
            var projectSelect = document.getElementById('detailProjectEdit');
            var previousProject = projectSelect.value;
            projectSelect.innerHTML = '<option value="">Select Project...</option>';
            projectSelect.add(new Option('Office', 'office'));
            // Run this step if the type equals direct.
            if (type === 'direct') financeProjects.forEach(function(project) {
                projectSelect.add(new Option(project.project_name, project.project_id));
            });
            projectSelect.value = type === 'direct' ? previousProject : 'office';
            updateDetailExpenseCategory();
        }

        // Update detail expense category.
        function updateDetailExpenseCategory() {
            var id = document.getElementById('detailCategoryEdit').value;
            // Run the find action.
            var category = financeCategories.find(function(item) {
                return String(item.fin_category_id || item.expense_category_id) === id;
            });
            var type = expenseTypeForCategory(category);
            var showProject = type === 'direct' || type === 'admin';
            document.getElementById('detailProjectEdit').style.display = isEditMode && showProject ? '' : 'none';
            // Run this step if the show project is missing or false.
            if (!showProject) document.getElementById('detailProjectEdit').value = '';
        }

        // Format cost component.
        function formatCostComponent(component) {
            return PROJECT_COST_COMPONENT_LABELS[component] || '—';
        }

        // Dynamic expense fields
        function toggleExpenseAmountFields() {
            var categorySelect = document.getElementById('expenseCategory');
            document.getElementById('addExpenseModalTitle').textContent = 'Add Expense';
            // Run this step if the category select value is missing or false.
            if (!categorySelect.value) document.getElementById('expenseAmount').value = '';
        }

        // Expense detail file selection
        function handleDetailFileSelect(input) {
            var fileNameDisplay = document.getElementById('expenseFileNameDisplay');
            var fileIcon = document.getElementById('expenseFileIcon');
            var viewButton = document.getElementById('viewExpenseFileBtn');
            var deleteButton = document.getElementById('deleteExpenseFileBtn');
            var previewContainer = document.getElementById('filePreviewContainer');
            var previewImage = document.getElementById('filePreviewImage');

            // Run this step if the files is missing or false or the files length is missing or false.
            if (!input.files || !input.files.length) {
                selectedDetailFile = null;
                fileNameDisplay.textContent = 'No file attached';
                fileIcon.textContent = 'File';
                viewButton.style.display = 'none';
                deleteButton.style.display = 'none';
                previewContainer.style.display = 'none';
                return;
            }

            selectedDetailFile = input.files[0];
            expenseFileMarkedForRemoval = false;
            fileNameDisplay.textContent = selectedDetailFile.name;

            var fileType = selectedDetailFile.type || '';
            // Run this step if the starts with check matches.
            if (fileType.startsWith('image/')) {
                fileIcon.textContent = 'Image';
                // Run this step if the current expense file url is set.
                if (currentExpenseFileUrl) { URL.revokeObjectURL(currentExpenseFileUrl); }
                currentExpenseFileUrl = URL.createObjectURL(selectedDetailFile);
                previewImage.src = currentExpenseFileUrl;
                previewContainer.style.display = 'block';
            } else if (fileType === 'application/pdf') {
                fileIcon.textContent = 'PDF';
                previewContainer.style.display = 'none';
            } else {
                fileIcon.textContent = 'File';
                previewContainer.style.display = 'none';
            }

            viewButton.style.display = 'inline-block';
            deleteButton.style.display = 'inline-block';
        }

        // Get the view expense file.
        function viewExpenseFile() {
            // Run this step if the selected detail file is set.
            if (selectedDetailFile) {
                var fileUrl = URL.createObjectURL(selectedDetailFile);
                window.open(fileUrl, '_blank');
                // Run this action after the delay.
                setTimeout(function() { URL.revokeObjectURL(fileUrl); }, 60000);
                return;
            }
            var viewButton = document.getElementById('viewExpenseFileBtn');
            var savedUrl = viewButton ? viewButton.getAttribute('data-file-url') : null;
            // Run this step if the saved url is set.
            if (savedUrl) { window.open(savedUrl, '_blank'); return; }
            showError('No file is currently attached.');
        }

        // Delete expense file.
        function deleteExpenseFile() {
            selectedDetailFile = null;
            expenseFileMarkedForRemoval = true;
            var detailFileInput = document.getElementById('detailExpenseFile');
            // Run this step if the detail file input is set.
            if (detailFileInput) detailFileInput.value = '';

            var fileNameDisplay = document.getElementById('expenseFileNameDisplay');
            var fileIcon = document.getElementById('expenseFileIcon');
            var viewButton = document.getElementById('viewExpenseFileBtn');
            var deleteButton = document.getElementById('deleteExpenseFileBtn');
            var previewContainer = document.getElementById('filePreviewContainer');

            // Run this step if the file name display is set.
            if (fileNameDisplay) fileNameDisplay.textContent = 'No file attached';
            // Run this step if the file icon is set.
            if (fileIcon) fileIcon.textContent = 'File';
            // Run this step if the view button is set.
            if (viewButton) viewButton.style.display = 'none';
            // Run this step if the delete button is set.
            if (deleteButton) deleteButton.style.display = 'none';
            // Run this step if the preview container is set.
            if (previewContainer) previewContainer.style.display = 'none';

            // Run this step if the current expense file url is set.
            if (currentExpenseFileUrl) { URL.revokeObjectURL(currentExpenseFileUrl); currentExpenseFileUrl = null; }
        }

        // Budget detail file selection
        function handleBudgetDetailFileSelect(input) {
            var fileNameDisplay = document.getElementById('budgetFileNameDisplay');
            var fileIcon = document.getElementById('budgetFileIcon');
            var viewButton = document.getElementById('viewBudgetFileBtn');
            var deleteButton = document.getElementById('deleteBudgetFileBtn');
            var previewContainer = document.getElementById('budgetFilePreviewContainer');
            var previewImage = document.getElementById('budgetFilePreviewImage');

            // Run this step if the files is missing or false or the files length is missing or false.
            if (!input.files || !input.files.length) {
                selectedBudgetDetailFile = null;
                fileNameDisplay.textContent = 'No file attached';
                fileIcon.textContent = 'File';
                viewButton.style.display = 'none';
                deleteButton.style.display = 'none';
                previewContainer.style.display = 'none';
                return;
            }

            selectedBudgetDetailFile = input.files[0];
            budgetFileMarkedForRemoval = false;
            fileNameDisplay.textContent = selectedBudgetDetailFile.name;

            var fileType = selectedBudgetDetailFile.type || '';
            // Run this step if the starts with check matches.
            if (fileType.startsWith('image/')) {
                fileIcon.textContent = 'Image';
                // Run this step if the current budget file url is set.
                if (currentBudgetFileUrl) { URL.revokeObjectURL(currentBudgetFileUrl); }
                currentBudgetFileUrl = URL.createObjectURL(selectedBudgetDetailFile);
                previewImage.src = currentBudgetFileUrl;
                previewContainer.style.display = 'block';
            } else if (fileType === 'application/pdf') {
                fileIcon.textContent = 'PDF';
                previewContainer.style.display = 'none';
            } else {
                fileIcon.textContent = 'File';
                previewContainer.style.display = 'none';
            }

            viewButton.style.display = 'inline-block';
            deleteButton.style.display = 'inline-block';
        }

        // Get the view budget file.
        function viewBudgetFile() {
            // Run this step if the selected budget detail file is set.
            if (selectedBudgetDetailFile) {
                var fileUrl = URL.createObjectURL(selectedBudgetDetailFile);
                window.open(fileUrl, '_blank');
                // Run this action after the delay.
                setTimeout(function() { URL.revokeObjectURL(fileUrl); }, 60000);
                return;
            }
            var viewButton = document.getElementById('viewBudgetFileBtn');
            var savedUrl = viewButton ? viewButton.getAttribute('data-file-url') : null;
            // Run this step if the saved url is set.
            if (savedUrl) { window.open(savedUrl, '_blank'); return; }
            showError('No file is currently attached.');
        }

        // Delete budget file.
        function deleteBudgetFile() {
            selectedBudgetDetailFile = null;
            budgetFileMarkedForRemoval = true;
            var detailFileInput = document.getElementById('detailBudgetFile');
            // Run this step if the detail file input is set.
            if (detailFileInput) detailFileInput.value = '';

            var fileNameDisplay = document.getElementById('budgetFileNameDisplay');
            var fileIcon = document.getElementById('budgetFileIcon');
            var viewButton = document.getElementById('viewBudgetFileBtn');
            var deleteButton = document.getElementById('deleteBudgetFileBtn');
            var previewContainer = document.getElementById('budgetFilePreviewContainer');

            // Run this step if the file name display is set.
            if (fileNameDisplay) fileNameDisplay.textContent = 'No file attached';
            // Run this step if the file icon is set.
            if (fileIcon) fileIcon.textContent = 'File';
            // Run this step if the view button is set.
            if (viewButton) viewButton.style.display = 'none';
            // Run this step if the delete button is set.
            if (deleteButton) deleteButton.style.display = 'none';
            // Run this step if the preview container is set.
            if (previewContainer) previewContainer.style.display = 'none';

            // Run this step if the current budget file url is set.
            if (currentBudgetFileUrl) { URL.revokeObjectURL(currentBudgetFileUrl); currentBudgetFileUrl = null; }
        }

        // Set up proof file upload.
        function initializeProofFileUpload() {
            var expenseProofFile = document.getElementById('expenseProofFile');
            var expenseProofFileName = document.getElementById('expenseProofFileName');
            // Run this step if the expense proof file is set.
            if (expenseProofFile) {
                // Handle the change event.
                expenseProofFile.addEventListener('change', function() {
                    expenseProofFileName.textContent = (this.files && this.files.length > 0) ? this.files[0].name : 'No file chosen';
                });
            }
            var budgetProofFile = document.getElementById('budgetProofFile');
            var budgetProofFileName = document.getElementById('budgetProofFileName');
            // Run this step if the budget proof file is set.
            if (budgetProofFile) {
                // Handle the change event.
                budgetProofFile.addEventListener('change', function() {
                    budgetProofFileName.textContent = (this.files && this.files.length > 0) ? this.files[0].name : 'No file chosen';
                });
            }
        }

        // Get the api fetch.
        function apiFetch(endpoint, options) {
            var isFormData = options && options.body instanceof FormData;
            var headers = {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            };

            // Run this step if the is form data is missing or false and the options is set and the headers is set.
            if (!isFormData && options && options.headers) {
                headers = { ...headers, ...options.headers };
            }

            // Use the result when this request finishes.
            return fetch(API_BASE + endpoint, {
                ...options,
                headers: isFormData ? headers : { ...headers, 'Content-Type': 'application/json' },
                credentials: 'same-origin'
            }).then(function(response) {
                // Return early if the response ok is missing or false.
                if (!response.ok) {
                    // Use the result when this request finishes.
                    return response.text().then(function(text) {
                        var data = null;
                        // Try this action and handle any errors below.
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

        // Tab switching
        function switchBudgetContractsSubtab(tab, updateUrl) {
            var contracts = tab === 'contracts';
            document.getElementById('budgetsSubpanel').hidden = contracts;
            document.getElementById('contractsSubpanel').hidden = !contracts;
            // Process each name.
            ['budgets', 'contracts'].forEach(function(name) {
                var button = document.getElementById(name + 'Subtab');
                var selected = name === (contracts ? 'contracts' : 'budgets');
                button.classList.toggle('active', selected);
                button.setAttribute('aria-selected', String(selected));
            });
            document.getElementById('addBudgetHeaderButton').hidden = contracts;
            document.getElementById('addContractHeaderButton').hidden = !contracts;
            updateFinanceImportAction(contracts ? 'contracts' : 'budgets');
            // Run this step if the update url does not equal false.
            if (updateUrl !== false) {
                var url = new URL(window.location.href);
                // Run this step if the contracts is set.
                if (contracts) url.searchParams.set('subtab', 'contracts');
                else url.searchParams.delete('subtab');
                history.replaceState(null, '', url);
            }
            // Run this step if the contracts is set.
            if (contracts) loadProfit();
            else filterBudgetTable();
        }

        // Update finance import action.
        function updateFinanceImportAction(section) {
            var button = document.getElementById('financeImportButton');
            button.closest('.finance-header-action-group').hidden = section === 'budgets';
            var label = section === 'contracts' ? 'Import Contracts' : 'Import Expenses';
            var text = button.querySelector('.button-label');
            // Run this step if the text is set.
            if (text) text.textContent = label;
            else button.textContent = label;
            button.setAttribute('aria-label', label);
            button.title = label;
        }

        // Get the switch report tab.
        function switchReportTab(tab) {
            currentReportTab = tab;
            var headerActions = document.getElementById('financeHeaderActions');
            // Run this step if the header actions is set.
            if (headerActions) {
                var hasActiveHeaderAction = false;
                // Process each group.
                headerActions.querySelectorAll('[data-finance-tabs]').forEach(function(group) {
                    var isActive = group.dataset.financeTabs.split(/\s+/).indexOf(tab) !== -1;
                    group.hidden = !isActive;
                    hasActiveHeaderAction = hasActiveHeaderAction || isActive;
                });
                headerActions.hidden = !hasActiveHeaderAction;
            }
            // Process each el.
            document.querySelectorAll('.report-section').forEach(function(el) {
                el.classList.toggle('active', el.id === 'tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
            });

            // Choose the action for the tab.
            switch(tab) {
                case 'expenses': 
                    updateFinanceImportAction('expenses');
                    applyFilters(); 
                    break;
                case 'budgets': 
                    // Make sure budget data is loaded
                    if (budgetData.length === 0) {
                        fetchBudgetData();
                    } else if (new URLSearchParams(window.location.search).get('subtab') === 'contracts') {
                        switchBudgetContractsSubtab('contracts', false);
                    } else {
                        filterBudgetTable();
                    }
                    break;
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

        // Handle the pfims autorefresh event.
        document.addEventListener('pfims:autorefresh', function() {
            // Run this step if the current report tab equals expenses.
            if (currentReportTab === 'expenses') {
                fetchExpenses();
                fetchAssets();
                return;
            }
            // Run this step if the current report tab equals budgets.
            if (currentReportTab === 'budgets') {
                fetchBudgetData();
                return;
            }
            switchReportTab(currentReportTab);
        });

        // Delete modals
        function openDeleteModal(message, callback, editing) {
            document.querySelector('#deleteConfirmModal h2').textContent = editing ? 'Confirm Update' : 'Confirm Deletion';
            var confirmButton = document.getElementById('confirmDeleteBtn');
            confirmButton.textContent = editing ? 'Update' : 'Delete';
            confirmButton.className = editing ? 'btn-save' : 'btn-delete';
            document.querySelector('#deleteConfirmModal .modal-body p:last-child').textContent = editing ? 'This updates all records in the selected asset and period.' : 'You can restore deleted records from Settings → Trash Bin for 30 days before permanent deletion.';
            document.getElementById('deleteConfirmMessage').textContent = message || 'Are you sure you want to move this item to Trash Bin?';
            deleteCallback = callback;
            document.getElementById('deleteConfirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Close delete modal.
        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
            document.body.style.overflow = '';
            deleteCallback = null;
        }

        // Get the confirm delete.
        function confirmDelete() {
            // Run this step if the delete callback equals function.
            if (typeof deleteCallback === 'function') { deleteCallback(); }
            closeDeleteModal();
        }

        // Handle the click event.
        document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) closeDeleteModal();
        });

        // Open budget delete modal.
        function openBudgetDeleteModal(message, callback) {
            document.getElementById('deleteBudgetConfirmMessage').textContent = message || 'Are you sure you want to move this budget to Trash Bin?';
            budgetDeleteCallback = callback;
            document.getElementById('deleteBudgetConfirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Close budget delete modal.
        function closeBudgetDeleteModal() {
            document.getElementById('deleteBudgetConfirmModal').style.display = 'none';
            document.body.style.overflow = '';
            budgetDeleteCallback = null;
        }

        // Get the confirm budget delete.
        function confirmBudgetDelete() {
            // Run this step if the budget delete callback equals function.
            if (typeof budgetDeleteCallback === 'function') { budgetDeleteCallback(); }
            closeBudgetDeleteModal();
        }

        // Handle the click event.
        document.getElementById('deleteBudgetConfirmModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) closeBudgetDeleteModal();
        });

        // Open bond delete modal.
        function openBondDeleteModal(message, callback) {
            document.getElementById('deleteBondConfirmMessage').textContent = message || 'Are you sure you want to move this bond to Trash Bin?';
            bondDeleteCallback = callback;
            document.getElementById('deleteBondConfirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Close bond delete modal.
        function closeBondDeleteModal() {
            document.getElementById('deleteBondConfirmModal').style.display = 'none';
            document.body.style.overflow = '';
            bondDeleteCallback = null;
        }

        // Get the confirm bond delete.
        function confirmBondDelete() {
            // Run this step if the bond delete callback equals function.
            if (typeof bondDeleteCallback === 'function') { bondDeleteCallback(); }
            closeBondDeleteModal();
        }

        // Handle the click event.
        document.getElementById('deleteBondConfirmModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) closeBondDeleteModal();
        });

        // Lightweight client-side filtering for the report tabs whose data is
        // loaded asynchronously. Keeping the rows in place preserves each
        // row's existing detail-modal click handler.
        function filterFinanceRows(inputId, bodyId) {
            var input = document.getElementById(inputId);
            var body = document.getElementById(bodyId);
            // Return early if the input is missing or false or the body is missing or false.
            if (!input || !body) return;
            var term = input.value.toLowerCase().trim();
            // Process each row.
            body.querySelectorAll('tr').forEach(function(row) {
                // Return early if the contains check matches.
                if (row.classList.contains('total-row')) return;
                row.style.display = !term || row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
            body.closest('table').pfimsRefreshPagination?.();
        }

        // Clear profit search.
        function clearProfitSearch() {
            document.getElementById('profitSearch').value = '';
            document.getElementById('profitYear').value = '';
            loadProfit();
        }

        // Clear receivable search.
        function clearReceivableSearch() {
            document.getElementById('receivableSearch').value = '';
            document.getElementById('receivableType').value = 'accounts_receivable';
            document.getElementById('receivableMonth').value = '';
            loadReceivables();
        }

        // Clear bond search.
        function clearBondSearch() {
            document.getElementById('bondSearch').value = '';
            document.getElementById('bondProjectFilter').value = 'all';
            document.getElementById('bondStatusFilter').value = 'all';
            document.getElementById('bondMonth').value = '';
            loadBonds();
        }

        // Expense filters
        function setActiveTab(el, period) {
            // Process each tab.
            document.querySelectorAll('.filter-tabs .tab').forEach(function(tab) { tab.classList.remove('active'); });
            el.classList.add('active');
            currentPeriod = period;
            applyFilters();
        }

        // Filter by project.
        function filterByProject() {
            currentProjectFilter = document.getElementById('projectFilter').value;
            applyFilters();
        }

        // Clear search.
        function clearSearch() {
            document.getElementById('projectSearch').value = '';
            document.getElementById('projectFilter').value = 'all';
            document.getElementById('expenseScopeFilter').value = 'all';
            document.getElementById('expenseRecordStatusFilter').value = 'all';
            document.getElementById('expenseSourceFilter').value = 'all';
            document.getElementById('expenseCategoryFilter').value = 'all';
            currentSearchTerm = '';
            currentProjectFilter = 'all';
            applyFilters();
        }

        // Apply filters.
        function applyFilters() {
            // Return early if the current report tab does not equal expenses.
            if (currentReportTab !== 'expenses') return;

            var searchTerm = document.getElementById('projectSearch').value.toLowerCase().trim();
            var categoryFilter = document.getElementById('expenseCategoryFilter').value;
            var scopeFilter = document.getElementById('expenseScopeFilter').value;
            var recordStatusFilter = document.getElementById('expenseRecordStatusFilter').value;
            var sourceFilter = document.getElementById('expenseSourceFilter').value;
            currentSearchTerm = searchTerm;

            // Keep the matching expense items.
            var projectFiltered = currentProjectFilter === 'all' ? financeExpenses : financeExpenses.filter(function(expense) {
                return !isOfficeExpense(expense) && String(expense.project_id || '') === currentProjectFilter;
            });

            // Keep the matching expense items.
            var scopeFiltered = scopeFilter === 'all' ? projectFiltered : projectFiltered.filter(function(expense) {
                var categoryId = String(expense.fin_category_id || expense.expense_category_id || '');
                // Run the find action.
                var category = financeCategories.find(function(item) { return String(item.fin_category_id || item.expense_category_id || '') === categoryId; });
                var classification = String(category && category.classification || '').toLowerCase();
                return (scopeFilter === 'overall' && ['direct', 'admin', 'office'].includes(classification))
                    || (scopeFilter === 'direct' && classification === 'direct')
                    || (scopeFilter === 'admin' && classification === 'admin')
                    || (scopeFilter === 'office' && classification === 'office');
            });
            // Keep the matching expense items.
            var categoryFiltered = categoryFilter === 'all'
                ? scopeFiltered
                : scopeFiltered.filter(function(expense) { return String(expense.fin_category_id || expense.expense_category_id || '') === String(categoryFilter); });

            // Keep the matching expense items.
            var recordStatusFiltered = recordStatusFilter === 'all' ? categoryFiltered : categoryFiltered.filter(function(expense) {
                var hasProject = expense.project_id !== null && expense.project_id !== undefined && String(expense.project_id).trim() !== '';
                var amount = expense.amount === null || expense.amount === undefined || String(expense.amount).trim() === '' ? NaN : Number(expense.amount);
                var isMissingAmount = !Number.isFinite(amount) || amount <= 0;
                // Return early if the record status filter equals missing_amount.
                if (recordStatusFilter === 'missing_amount') return isMissingAmount;
                // Return early if the record status filter equals no_project.
                if (recordStatusFilter === 'no_project') return !hasProject;
                // Return early if the record status filter equals missing_amount_and_project.
                if (recordStatusFilter === 'missing_amount_and_project') return isMissingAmount && !hasProject;
                return true;
            });

            // Keep the matching expense items.
            var sourceFiltered = sourceFilter === 'all' ? recordStatusFiltered : recordStatusFiltered.filter(function(expense) {
                var isInventoryExpense = expense.is_inventory_expense === true || expense.entry_kind === 'inventory_purchase'
                    || (expense.inventory_transaction_id !== null && expense.inventory_transaction_id !== undefined
                        && String(expense.inventory_transaction_id).trim() !== '');
                return sourceFilter === 'inventory' ? isInventoryExpense : !isInventoryExpense;
            });

            var searchFiltered = sourceFiltered;
            // Run this step if the search term is set.
            if (searchTerm) {
                // Keep the matching expense items.
                searchFiltered = sourceFiltered.filter(function(expense) {
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

        // Filter by period.
        function filterByPeriod(expenses) {
            // If period is 'all', return all expenses
            if (currentPeriod === 'all') return expenses;
            
            var now = new Date();
            // Keep the matching expense items.
            return expenses.filter(function(expense) {
                // Return early if the expense date is missing or false.
                if (!expense.expense_date) return false;
                var expenseDate = new Date(expense.expense_date);
                // Choose the action for the current period.
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

        // SINGLE SOURCE OF TRUTH for "Total Budget": every KPI card on every
        // report tab must call this instead of re-summing budget_amount itself.
        // This is the fix for the Budgets tab / Expenses tab total mismatch:
        // previously updateFinanceTotals() summed `budgetData` while
        // updateBudgetStats() summed `budgetFilteredData`, and those two
        // arrays could fall out of sync (see deleteBudget() fix below).
        function calculateBudgetTotal(list) {
            // Return early if the list is missing or false or the list length is missing or false.
            if (!list || !list.length) return 0;
            // Combine the items into one result.
            return list.reduce(function(sum, item) {
                return sum + (parseFloat(item.budget_amount) || 0);
            }, 0);
        }

        // Calculate main budget remaining.
        function calculateCanonicalBudgetRemaining() {
            var budgets = budgetDataCache || budgetData || [];
            // Build a value for each item.
            var projectIds = new Set(budgets.map(function(item) { return String(item.project_id); }));
            var totalBudget = calculateBudgetTotal(budgets);
            // Combine the items into one result.
            var projectExpenses = financeExpenses.reduce(function(sum, expense) {
                return sum + (expense.project_id != null && projectIds.has(String(expense.project_id))
                    ? (parseFloat(expense.amount) || 0) : 0);
            }, 0);
            return totalBudget - projectExpenses;
        }

        // Update finance totals.
        function updateFinanceTotals() {
            // Build a value for each e.
            var filteredProjectIds = new Set(financeFilteredData.map(function(e) { return e.project_id == null ? '' : String(e.project_id); }).filter(Boolean));
            // Combine the items into one result.
            var totalBudget = (budgetDataCache || budgetData).reduce(function(sum, item) {
                return sum + (filteredProjectIds.has(String(item.project_id)) ? (parseFloat(item.budget_amount) || 0) : 0);
            }, 0);

            // Combine the items into one result.
            var totalExpenses = financeFilteredData.reduce(function(sum, e) { 
                return sum + (parseFloat(e.amount) || 0); 
            }, 0);
            var netVariance = calculateCanonicalBudgetRemaining();

            // Run this step if the get element by id check matches.
            if (document.getElementById('totalBudgetValue')) document.getElementById('totalBudgetValue').textContent = formatCurrency(totalBudget);
            // Run this step if the get element by id check matches.
            if (document.getElementById('totalExpensesValue')) document.getElementById('totalExpensesValue').textContent = formatCurrency(totalExpenses);
            var varianceEl = document.getElementById('netVarianceValue');
            // Run this step if the variance el is set.
            if (varianceEl) {
                varianceEl.textContent = formatCurrency(netVariance);
                varianceEl.className = 'stat-value ' + (netVariance < 0 ? 'red' : 'green');
            }
        }

        // Update rows info.
        function updateRowsInfo(totalCount) {
            var rowsInfo = document.getElementById('rowsInfoText');
            // Return early if the rows info is missing or false.
            if (!rowsInfo) return;
            var currentPage = financeCurrentPage || 1;
            var pageSize = financePageSize || 10;
            var start = (currentPage - 1) * pageSize + 1;
            var end = Math.min(start + pageSize - 1, totalCount);
            rowsInfo.textContent = 'Total: ' + totalCount + ' expense' + (totalCount === 1 ? '' : 's');
        }

        // Api fetch functions
        function fetchProjects() {
            // Use the result when this request finishes.
            return fetch(API_BASE + '/projects', {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            })
            .then(function(response) {
                // Run this step if the response ok is missing or false.
                if (!response.ok) throw new Error('Failed to load projects.');
                return response.json();
            })
            .then(function(data) {
                financeProjects = data || [];
                // Build a value for each project.
                var contractYears = Array.from(new Set(financeProjects.map(function(project) {
                    return String(project.start_date || '').slice(0, 4);
                }).filter(function(year) { return /^\d{4}$/.test(year); }))).sort().reverse();
                // Build a value for each year.
                document.getElementById('profitYear').innerHTML = '<option value="">All Years</option>' + contractYears
                    .map(function(year) { return '<option value="' + year + '">' + year + '</option>'; }).join('');
                populateProjectDropdowns();
                populateProjectFilter();
                populateBudgetProjectFilter();
                populateBondProjectFilter();
                // Run this step if the current report tab equals expenses.
                if (currentReportTab === 'expenses') updateFinanceTotals();
            })
            .catch(function(error) {
                console.error('fetchProjects error:', error);
                showError(error.message);
            });
        }

        // Load expense categories.
        function fetchExpenseCategories() {
            // Use the result when this request finishes.
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

        // Load expenses.
        function fetchExpenses() {
            // Use the result when this request finishes.
            return apiFetch('/finance-expenses')
                .then(function(data) {
                    financeExpenses = data || [];
                    financeFilteredData = financeExpenses;
                    
                    // CRITICAL: Load budget data FIRST before updating UI
                    return fetchBudgetData();
                })
                .then(function() {
                    // Run this step if the current report tab equals expenses.
                    if (currentReportTab === 'expenses') {
                        applyFilters();
                    }
                })
                .catch(function(error) { 
                    showError(error.message); 
                });
        }

        // Load assets.
        function fetchAssets() {
            // Use the result when this request finishes.
            return fetch(API_BASE + '/company-assets', {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            })
            .then(function(response) {
                // Run this step if the response ok is missing or false.
                if (!response.ok) {
                    throw new Error('Failed to fetch assets');
                }
                return response.json();
            })
            .then(function(data) {
                // Build a value for each item.
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

        var budgetDataCache = null;

        // Load budget data.
        function fetchBudgetData() {
            // If we already have cached budget data, use it
            if (budgetDataCache !== null) {
                budgetData = budgetDataCache;
                updateBudgetActualAmounts();
                filterBudgetTable();
                updateFinanceTotals();
                return Promise.resolve(budgetData);
            }

            // Use the result when this request finishes.
            return fetch(API_BASE + '/budgets', {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            })
            .then(function(response) {
                // Run this step if the response ok is missing or false.
                if (!response.ok) throw new Error('Failed to load budgets.');
                return response.json();
            })
            .then(function(budgets) {
                // Use the result when this request finishes.
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
                    // Process each p.
                    projects.forEach(function(p) { projectMap[p.project_id] = p; });

                    // Build budget data from budgets table - SINGLE SOURCE OF TRUTH
                    var budgetDataFromBudgets = budgets.map(function(b) {
                        var project = projectMap[b.project_id] || {};
                        var budgetAmount = parseFloat(b.budget_amount) || 0;
                        return {
                            project_id: b.project_id,
                            project_name: project.project_name || 'Unknown Project',
                            budget_amount: budgetAmount,
                            actual_amount: parseFloat(b.actual_amount) || 0,
                            remaining: budgetAmount - (parseFloat(b.actual_amount) || 0),
                            budget_id: b.budget_id,
                            proof_file_path: b.proof_file_path || '',
                            proof_file_name: b.proof_file_name || '',
                            status: 'On Track'
                        };
                    });

                    // Add projects with expenses but no budget
                    var existingProjectIds = budgetDataFromBudgets.map(function(b) { return b.project_id; });
                    var projectsWithSpend = [];
                    // Process each project.
                    financeProjects.forEach(function(project) {
                        // Run this step if the index of equals the value.
                        if (existingProjectIds.indexOf(project.project_id) === -1) {
                            projectsWithSpend.push({
                                project_id: project.project_id,
                                project_name: project.project_name || 'Unknown Project',
                                budget_amount: 0,
                                actual_amount: 0,
                                remaining: 0,
                                budget_id: null,
                                proof_file_path: '',
                                proof_file_name: '',
                                status: 'No Budget'
                            });
                        }
                    });

                    // Cache the budget data
                    budgetDataCache = budgetDataFromBudgets.concat(projectsWithSpend);
                    budgetData = budgetDataCache;

                    updateBudgetActualAmounts();

                    filterBudgetTable();
                    updateFinanceTotals();
                    
                    return budgetData;
                });
            })
            .catch(function(error) {
                showError('Error loading budget data: ' + error.message);
                document.getElementById('budgetTableBody').innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:#d32f2f;">Error loading budget data</td></tr>';
                throw error;
            });
        }

        // Update budget actual amounts.
        function updateBudgetActualAmounts() {
            // Use the server's main project cost (direct expenses plus stock-out allocations).
            budgetData.forEach(function(item) {
                var actualSpend = parseFloat(item.actual_amount) || 0;
                item.remaining = item.budget_amount - actualSpend;
                var budgetAmount = item.budget_amount;
                item.status = budgetAmount === 0 ? 'No Budget' : 
                    (actualSpend > budgetAmount ? 'Over Budget' : 
                    (actualSpend > budgetAmount * 0.9 ? 'Near Limit' : 'On Track'));
            });
        }

        // Populate dropdowns
        function populateProjectDropdowns() {
            var selects = ['budgetProject', 'budgetDetailProjectEdit', 'detailProjectEdit', 'bondProject', 'bondDetailProjectEdit', 'receivableProject', 'backhoeExpenseProject', 'backhoeRentalProject', 'contractProject', 'receivableDetailProjectEdit'];
            // Process each id.
            selects.forEach(function(id) {
                var select = document.getElementById(id);
                // Return early if the select is missing or false.
                if (!select) return;
                select.innerHTML = '<option value="">Select Project...</option>';
                // Run this step if the id equals detailProjectEdit.
                if (id === 'detailProjectEdit') select.add(new Option('Office', 'office'));
                // Process each project.
                financeProjects.forEach(function(project) {
                    var option = document.createElement('option');
                    option.value = project.project_id;
                    option.textContent = project.project_name;
                    select.appendChild(option);
                });
            });
            updateExpenseType();
        }

        // Get the populate category dropdown.
        function populateCategoryDropdown() {
            var selects = ['expenseCategory', 'detailCategoryEdit'];
            // Process each id.
            selects.forEach(function(id) {
                var select = document.getElementById(id);
                // Return early if the select is missing or false.
                if (!select) return;
                var currentValue = select.value;
                var type = document.getElementById(id === 'expenseCategory' ? 'expenseType' : 'detailExpenseTypeEdit').value;
                filterExpenseCategorySelect(id, type, currentValue);
            });
        }

        // Get the populate project filter.
        function populateProjectFilter() {
            var filter = document.getElementById('projectFilter');
            filter.innerHTML = '<option value="all">All Projects</option>';
            // Process each project.
            financeProjects.forEach(function(project) {
                var option = document.createElement('option');
                option.value = project.project_id;
                option.textContent = project.project_name;
                filter.appendChild(option);
            });
        }

        // Get the populate category filter.
        function populateCategoryFilter() {
            var filter = document.getElementById('expenseCategoryFilter');
            // Return early if the filter is missing or false.
            if (!filter) return;
            var currentValue = filter.value || 'all';
            filter.innerHTML = '<option value="all">All Categories</option>';
            // Process each category.
            financeCategories.forEach(function(category) {
                var option = document.createElement('option');
                option.value = category.fin_category_id || category.expense_category_id;
                option.textContent = category.category_name || category.category_code || category;
                filter.appendChild(option);
            });
            filter.value = currentValue;
        }

        // Get the populate budget project filter.
        function populateBudgetProjectFilter() {
            var filter = document.getElementById('budgetProjectFilter');
            filter.innerHTML = '<option value="all">All Projects</option>';
            // Process each project.
            financeProjects.forEach(function(project) {
                var option = document.createElement('option');
                option.value = project.project_name;
                option.textContent = project.project_name;
                filter.appendChild(option);
            });
        }

        // Get the populate asset dropdowns.
        function populateAssetDropdowns() {
            var selects = ['repairAssetSelect', 'backhoeExpenseAsset', 'backhoeRentalAsset', 'repairAsset'];

            // Process each id.
            selects.forEach(function(id) {
                var select = document.getElementById(id);
                // Return early if the select is missing or false.
                if (!select) return;

                var currentValue = select.value;
                select.innerHTML = '<option value="">Select Asset...</option>';

                // Process each asset.
                assets.forEach(function(asset) {
                    var option = document.createElement('option');
                    option.value = asset.asset_id;
                    option.textContent = asset.asset_name;
                    select.appendChild(option);
                });

                // Run this step if the current value is set.
                if (currentValue) {
                    var options = select.querySelectorAll('option');
                    // Repeat for each step in this range.
                    for (var i = 0; i < options.length; i++) {
                        // Run this step if the i value equals the current value.
                        if (options[i].value === currentValue) {
                            select.value = currentValue;
                            break;
                        }
                    }
                }
            });

        }

        // Get the populate cash account dropdown.
        function populateCashAccountDropdown(message) {
            var select = document.getElementById('cashAccount');
            // Return early if the select is missing or false.
            if (!select) return;
            select.innerHTML = '';
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = message || (cashAccounts.length ? 'Select Account...' : 'No accounts available');
            placeholder.disabled = Boolean(message) || cashAccounts.length === 0;
            placeholder.selected = true;
            select.appendChild(placeholder);
            // Process each account.
            cashAccounts.forEach(function(account) {
                var option = document.createElement('option');
                option.value = account.account_id;
                option.textContent = account.account_name;
                select.appendChild(option);
            });
            select.disabled = Boolean(message) || cashAccounts.length === 0;
        }

        // Load cash accounts.
        function fetchCashAccounts() {
            cashAccounts = [];
            populateCashAccountDropdown('Loading accounts...');
            // Use the result when this request finishes.
            return apiFetch('/cash-accounts')
                .then(function(data) {
                    // Keep the matching account items.
                    cashAccounts = Array.isArray(data) ? data.filter(function(account) {
                        return account && account.account_id && account.account_name;
                    }) : [];
                    populateCashAccountDropdown();
                    return cashAccounts;
                })
                .catch(function(error) {
                    populateCashAccountDropdown('Unable to load accounts');
                    throw error;
                });
        }

        // Open add cash account modal.
        function openAddCashAccountModal() {
            document.getElementById('addCashAccountModal').dataset.accountId = '';
            document.querySelector('#addCashAccountModal h2').textContent = 'Add Account';
            document.getElementById('saveCashAccountButton').textContent = 'Add Account';
            document.getElementById('cashAccountTitle').value = '';
            document.getElementById('addCashAccountModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('cashAccountTitle').focus();
        }

        // Close add cash account modal.
        function closeAddCashAccountModal() {
            document.getElementById('addCashAccountModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Save cash account.
        function saveCashAccount() {
            var title = document.getElementById('cashAccountTitle').value.trim();
            // Run this step if the title is missing or false.
            if (!title) {
                showError('Account title is required.');
                return;
            }
            var button = document.getElementById('saveCashAccountButton');
            button.disabled = true;
            var accountId = document.getElementById('addCashAccountModal').dataset.accountId;
            // Use the result when this request finishes.
            apiFetch('/cash-accounts' + (accountId ? '/' + accountId : ''), { method: accountId ? 'PUT' : 'POST', body: JSON.stringify({ account_name: title }) })
                .then(function(account) {
                    closeAddCashAccountModal();
                    showSuccess(accountId ? 'Account renamed.' : 'Account added. Enter its balance to display it in Cash Asset.');
                    loadCashAsset();
                    return openAddCashModal(account.account_id);
                })
                .catch(function(error) { showError(error.message); })
                .finally(function() { button.disabled = false; });
        }

        // Handle the click event.
        document.getElementById('addCashAccountModal').addEventListener('click', function(event) {
            // Run this step if the target equals the value.
            if (event.target === this) closeAddCashAccountModal();
        });

        // Get the edit selected cash account.
        function editSelectedCashAccount() {
            // Run the find action.
            var account = cashAccounts.find(function(item) { return String(item.account_id) === document.getElementById('cashAccount').value; });
            // Run this step if the account is missing or false.
            if (!account) { showError('Select an account first.'); return; }
            closeAddCashModal();
            openAddCashAccountModal();
            document.getElementById('addCashAccountModal').dataset.accountId = account.account_id;
            document.getElementById('cashAccountTitle').value = account.account_name;
            document.querySelector('#addCashAccountModal h2').textContent = 'Rename Account';
            document.getElementById('saveCashAccountButton').textContent = 'Save Changes';
        }

        // Delete selected cash account.
        function deleteSelectedCashAccount() {
            var id = document.getElementById('cashAccount').value;
            // Run this step if the id is missing or false.
            if (!id) { showError('Select an account first.'); return; }
            // Run the open bond delete modal action.
            openBondDeleteModal('Move this unused account to Trash Bin?', function() {
                // Use the result when this request finishes.
                apiFetch('/cash-accounts/' + id, {method: 'DELETE'})
                    .then(function() { showSuccess('Account deleted.'); return openAddCashModal(); })
                    .catch(function(error) { showError(error.message); });
            });
        }
        // Handle the keydown event.
        document.getElementById('cashAccountTitle').addEventListener('keydown', function(event) {
            // Run this step if the key equals Enter.
            if (event.key === 'Enter') saveCashAccount();
        });

        // Get the populate bond project filter.
        function populateBondProjectFilter() {
            var filter = document.getElementById('bondProjectFilter');
            // Return early if the filter is missing or false.
            if (!filter) return;

            filter.innerHTML = '<option value="all">All Projects</option>';
            // Process each project.
            financeProjects.forEach(function(project) {
                var option = document.createElement('option');
                option.value = project.project_id;
                option.textContent = project.project_name;
                filter.appendChild(option);
            });
        }

        // Render expenses
        function renderFinancePage(page) {
            financeCurrentPage = page;
            var start = (page - 1) * financePageSize;
            var end = Math.min(start + financePageSize, financeFilteredData.length);
            var pageData = financeFilteredData.slice(start, end);

            var tbody = document.getElementById('expenseTableBody');
            tbody.innerHTML = '';

            // Run this step if the page data length is missing or false.
            if (!pageData.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:20px;">No expenses found.</td></tr>';
                renderFinancePagination();
                updateRowsInfo(0);
                return;
            }

            // Process each expense.
            pageData.forEach(function(expense) {
                var row = document.createElement('tr');
                row.setAttribute('data-expense-id', expense.fin_expense_id || expense.expense_id);
                row.setAttribute('data-project-id', expense.project_id || '');
                row.setAttribute('data-project', expense.project_name || '');
                row.setAttribute('data-desc', expense.expense_description || '');
                row.setAttribute('data-category-id', expense.fin_category_id || expense.expense_category_id || '');
                row.setAttribute('data-category', expense.category_name || '');
                row.setAttribute('data-cost-component', expense.project_cost_component || '');
                row.setAttribute('data-amount', expense.amount === null || expense.amount === undefined ? '' : expense.amount);
                row.setAttribute('data-inventory-transaction-id', expense.inventory_transaction_id || '');
                row.setAttribute('data-is-inventory-expense', expense.is_inventory_expense === true || Boolean(expense.inventory_transaction_id) ? 'true' : 'false');
                row.setAttribute('data-is-pending-inventory', expense.is_pending_inventory === true ? 'true' : 'false');
                row.setAttribute('data-entry-kind', expense.entry_kind || '');
                row.setAttribute('data-date', expense.expense_date || '');
                row.setAttribute('data-remarks', expense.remarks || '');
                row.setAttribute('data-proof-file-path', expense.proof_file_path || '');
                row.setAttribute('data-proof-file-name', expense.proof_file_name || '');
                var isPendingInventory = expense.is_pending_inventory === true;
                var isInventoryExpense = expense.is_inventory_expense === true || Boolean(expense.inventory_transaction_id);
                row.style.cursor = isPendingInventory ? 'default' : 'pointer';
                // Run this step if the is pending inventory is missing or false.
                if (!isPendingInventory) row.onclick = function() { openExpenseModal(this); };

                var categoryName = expense.category_name || '';
                var categoryClass = categoryName.toLowerCase().replace(/[^a-z]/g, '-');
                // Check if admin category
                var isAdmin = false;
                var catCode = '';
                // Run the find action.
                var categoryObj = financeCategories.find(function(c) {
                    return (c.fin_category_id == expense.fin_category_id || c.expense_category_id == expense.expense_category_id);
                });
                // Run this step if the category obj is set.
                if (categoryObj) {
                    catCode = categoryObj.category_code || '';
                    isAdmin = isAdminCategory(catCode);
                }
                // Run this step if the is admin is set or the expense type for category equals office.
                if (isAdmin || expenseTypeForCategory(categoryObj) === 'office') categoryClass = 'admin';
                else if (['labor', 'material', 'equipment', 'other'].indexOf(categoryClass) === -1) categoryClass = 'other';

                var projectDisplay = isOfficeExpense(expense) ? 'Office Expenses' : (expense.project_name || (expense.entry_kind === 'inventory_purchase' ? 'For Storage' : '—'));
                var amountDisplay = expense.amount === null || expense.amount === undefined || String(expense.amount).trim() === ''
                    ? '—'
                    : formatCurrency(expense.amount);
                var actionButtons = isInventoryExpense
                    ? '<button type="button" class="pfims-row-action" onclick="event.stopPropagation(); openExpenseModal(this.closest(\'tr\'))" title="View expense details" aria-label="View expense details"><img src="/images/view.jpg" alt=""></button>'
                    : '—';
                row.innerHTML = '<td><strong>' + projectDisplay + '</strong></td>' +
                '<td>' + (expense.expense_description || '') + '</td>' +
                '<td><span class="category-badge ' + categoryClass + '">' + categoryName + '</span></td>' +
                '<td>' + formatCostComponent(expense.project_cost_component) + '</td>' +
                '<td>' + amountDisplay + '</td>' +
                '<td>' + (expense.expense_date || '') + '</td>' +
                '<td>' + (expense.remarks || '—') + '</td>' +
                '<td>' + actionButtons + '</td>';
                tbody.appendChild(row);
            });

            renderFinancePagination();
            updateRowsInfo(financeFilteredData.length);
        }

        // Open inventory expense modal.
        function openInventoryExpenseModal(transactionId) {
            // Run the find action.
            var pendingExpense = financeExpenses.find(function(expense) {
                return String(expense.inventory_transaction_id) === String(transactionId);
            });
            // Run this step if the pending expense is missing or false or the entry kind does not equal inventory_purchase.
            if (!pendingExpense || pendingExpense.entry_kind !== 'inventory_purchase') {
                showError('Historical stock-in needs inventory cost reconciliation before it can be edited.');
                return;
            }
            pendingInventoryTransactionId = transactionId;
            document.getElementById('inventoryExpenseAmount').value = pendingExpense && pendingExpense.amount !== null && pendingExpense.amount !== undefined ? pendingExpense.amount : '';
            document.getElementById('inventoryExpenseModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Close inventory expense modal.
        function closeInventoryExpenseModal() {
            document.getElementById('inventoryExpenseModal').classList.remove('active');
            document.body.style.overflow = '';
            document.getElementById('inventoryExpenseAmount').value = '';
            pendingInventoryTransactionId = null;
        }

        // Save inventory expense.
        function saveInventoryExpense() {
            var transactionId = pendingInventoryTransactionId;
            var amount = parseFloat(document.getElementById('inventoryExpenseAmount').value);
            // Run this step if the transaction id is missing or false or the amount is missing or false or the amount is below 0.01.
            if (!transactionId || !amount || amount < 0.01) {
                showError('Please enter a valid amount.');
                return;
            }

            var saveButton = document.getElementById('inventoryExpenseSaveBtn');
            saveButton.disabled = true;
            saveButton.textContent = 'Saving...';

            // Use the result when this request finishes.
            apiFetch('/finance-expenses/from-inventory/' + transactionId, {
                method: 'POST',
                body: JSON.stringify({ amount: amount })
            }).then(function() {
                return fetchExpenses();
            }).then(function() {
                applyFilters();
                closeInventoryExpenseModal();
                showSuccess('Stock-in expense updated.');
            }).catch(function(error) {
                showError(error.message || 'Unable to add stock-in expense.');
            }).finally(function() {
                saveButton.disabled = false;
                saveButton.textContent = 'Save Changes';
            });
        }

        // Show finance pagination.
        function renderFinancePagination() {
            var container = document.getElementById('financePaginationLinks');
            // Return early if the container is missing or false.
            if (!container) return;
            var total = financeFilteredData.length;
            var totalPages = Math.ceil(total / financePageSize);
            var current = financeCurrentPage;
            // Run this step if the total pages is at most 1.
            if (totalPages <= 1) { container.innerHTML = ''; return; }

            var html = '';
            html += '<a href="#" onclick="renderFinancePage(' + (current - 1) + '); return false;" class="' + (current <= 1 ? 'disabled' : '') + '">«</a>';

            // Run this step if the total pages is at most 7.
            if (totalPages <= 7) {
                // Repeat for each step in this range.
                for (var i = 1; i <= totalPages; i++) {
                    html += '<a href="#" onclick="renderFinancePage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
            } else {
                // Repeat for each step in this range.
                for (var i = 1; i <= 3; i++) html += '<a href="#" onclick="renderFinancePage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                // Run this step if the current is above 4.
                if (current > 4) html += '<span class="dots">...</span>';
                var startPage = Math.max(4, current - 1);
                var endPage = Math.min(totalPages - 2, current + 1);
                // Repeat for each step in this range.
                for (var i = startPage; i <= endPage; i++) html += '<a href="#" onclick="renderFinancePage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                // Run this step if the current is below the value.
                if (current < totalPages - 3) html += '<span class="dots">...</span>';
                // Repeat for each step in this range.
                for (var i = totalPages - 1; i <= totalPages; i++) {
                    // Run this step if the i is above 3.
                    if (i > 3) html += '<a href="#" onclick="renderFinancePage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
            }
            html += '<a href="#" onclick="renderFinancePage(' + (current + 1) + '); return false;" class="' + (current >= totalPages ? 'disabled' : '') + '">»</a>';
            container.innerHTML = html;
        }

        // Get the change finance page size.
        function changeFinancePageSize() {
            financePageSize = parseInt(document.getElementById('financeRowsPerPage').value) || 10;
            financeCurrentPage = 1;
            renderFinancePage(1);
        }

        // Render budgets
        function renderBudgetPage(page) {
            budgetCurrentPage = page;
            var start = (page - 1) * budgetPageSize;
            var end = Math.min(start + budgetPageSize, budgetFilteredData.length);
            var pageData = budgetFilteredData.slice(start, end);

            var tbody = document.getElementById('budgetTableBody');
            tbody.innerHTML = '';

            // Run this step if the page data length is missing or false.
            if (!pageData.length) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;">No budget data found.</td></tr>';
                renderBudgetPagination();
                updateBudgetRowsInfo(0);
                return;
            }

            // Process each budget.
            pageData.forEach(function(budget) {
                var row = document.createElement('tr');
                var budgetAmount = parseFloat(budget.budget_amount) || 0;
                var actualAmount = parseFloat(budget.actual_amount) || 0;
                var remaining = parseFloat(budget.remaining) || 0;
                var status = budget.status || 'No Budget';

                var rowClass = '';
                // Run this step if the remaining is below 0.
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
                // Run this step if the status equals On Track.
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

        // Show budget pagination.
        function renderBudgetPagination() {
            var container = document.getElementById('budgetPaginationLinks');
            // Return early if the container is missing or false.
            if (!container) return;
            var total = budgetFilteredData.length;
            var totalPages = Math.ceil(total / budgetPageSize);
            var current = budgetCurrentPage;
            // Run this step if the total pages is at most 1.
            if (totalPages <= 1) { container.innerHTML = ''; return; }

            var html = '';
            html += '<a href="#" onclick="renderBudgetPage(' + (current - 1) + '); return false;" class="' + (current <= 1 ? 'disabled' : '') + '">«</a>';

            // Run this step if the total pages is at most 7.
            if (totalPages <= 7) {
                // Repeat for each step in this range.
                for (var i = 1; i <= totalPages; i++) {
                    html += '<a href="#" onclick="renderBudgetPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
            } else {
                // Repeat for each step in this range.
                for (var i = 1; i <= 3; i++) html += '<a href="#" onclick="renderBudgetPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                // Run this step if the current is above 4.
                if (current > 4) html += '<span class="dots">...</span>';
                var startPage = Math.max(4, current - 1);
                var endPage = Math.min(totalPages - 2, current + 1);
                // Repeat for each step in this range.
                for (var i = startPage; i <= endPage; i++) html += '<a href="#" onclick="renderBudgetPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                // Run this step if the current is below the value.
                if (current < totalPages - 3) html += '<span class="dots">...</span>';
                // Repeat for each step in this range.
                for (var i = totalPages - 1; i <= totalPages; i++) {
                    // Run this step if the i is above 3.
                    if (i > 3) html += '<a href="#" onclick="renderBudgetPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
            }
            html += '<a href="#" onclick="renderBudgetPage(' + (current + 1) + '); return false;" class="' + (current >= totalPages ? 'disabled' : '') + '">»</a>';
            container.innerHTML = html;
        }

        // Get the change budget page size.
        function changeBudgetPageSize() {
            budgetPageSize = parseInt(document.getElementById('budgetRowsPerPage').value) || 10;
            budgetCurrentPage = 1;
            renderBudgetPage(1);
        }

        // Update budget rows info.
        function updateBudgetRowsInfo(totalCount) {
            var rowsInfo = document.getElementById('budgetRowsInfo');
            // Return early if the rows info is missing or false.
            if (!rowsInfo) return;
            var currentPage = budgetCurrentPage || 1;
            var pageSize = budgetPageSize || 10;
            var start = (currentPage - 1) * pageSize + 1;
            var end = Math.min(start + pageSize - 1, totalCount);
            rowsInfo.textContent = 'Total: ' + totalCount + ' project' + (totalCount === 1 ? '' : 's');
        }

        // Update budget stats.
        function updateBudgetStats() {
            // Combine the items into one result.
            var totalBudget = budgetFilteredData.reduce(function(sum, item) { return sum + (parseFloat(item.budget_amount) || 0); }, 0);
            // Combine the items into one result.
            var totalExpenses = budgetFilteredData.reduce(function(sum, item) {
                return sum + (parseFloat(item.actual_amount) || 0);
            }, 0);
            var netVariance = calculateCanonicalBudgetRemaining();

            // Run this step if the get element by id check matches.
            if (document.getElementById('budgetTotalValue')) document.getElementById('budgetTotalValue').textContent = formatCurrency(totalBudget);
            // Run this step if the get element by id check matches.
            if (document.getElementById('budgetSpentValue')) document.getElementById('budgetSpentValue').textContent = formatCurrency(totalExpenses);
            var budgetVarianceEl = document.getElementById('budgetRemainingValue');
            // Run this step if the budget variance el is set.
            if (budgetVarianceEl) {
                budgetVarianceEl.textContent = formatCurrency(netVariance);
                budgetVarianceEl.className = 'stat-value ' + (netVariance < 0 ? 'red' : 'green');
            }
        }

        // Filter budget table.
        function filterBudgetTable() {
            var searchTerm = document.getElementById('budgetSearch').value.toLowerCase().trim();
            var projectFilter = document.getElementById('budgetProjectFilter').value;
            var statusFilter = document.getElementById('budgetStatusFilter').value;

            budgetSearchTerm = searchTerm;
            budgetProjectFilter = projectFilter;

            // ALWAYS start from the cached budget data (same source for both tabs)
            var filtered = (budgetDataCache || budgetData).slice();

            // Budgets use the same project-cost ledger as the server and reports.
            filtered = filtered.map(function(item) {
                var actualSpend = parseFloat(item.actual_amount) || 0;
                var budgetAmount = parseFloat(item.budget_amount) || 0;
                var remaining = budgetAmount - actualSpend;
                return {
                    ...item,
                    actual_amount: actualSpend,
                    remaining: remaining,
                    status: budgetAmount === 0 ? 'No Budget' : 
                        (actualSpend > budgetAmount ? 'Over Budget' : 
                        (actualSpend > budgetAmount * 0.9 ? 'Near Limit' : 'On Track'))
                };
            });

            // --- Apply project filter ---
            if (projectFilter !== 'all') {
                // Keep the matching item items.
                filtered = filtered.filter(function(item) {
                    return item.project_name === projectFilter;
                });
            }

            // --- Apply status filter ---
            if (statusFilter !== 'all') {
                // Keep the matching item items.
                filtered = filtered.filter(function(item) {
                    return item.status === statusFilter;
                });
            }

            // --- Apply search filter ---
            if (searchTerm) {
                // Keep the matching item items.
                filtered = filtered.filter(function(item) {
                    return (item.project_name || '').toLowerCase().includes(searchTerm);
                });
            }

            budgetFilteredData = filtered;
            renderBudgetPage(1);
            updateBudgetStats();
        }

        // Clear budget search.
        function clearBudgetSearch() {
            document.getElementById('budgetSearch').value = '';
            document.getElementById('budgetProjectFilter').value = 'all';
            budgetSearchTerm = '';
            budgetProjectFilter = 'all';
            filterBudgetTable();
        }

        // Budget detail modal
        function openBudgetModal(row) {
            currentBudgetRow = row;
            document.getElementById('budgetDetailProjectDisplay').textContent = row.dataset.projectName;
            document.getElementById('budgetDetailAmountDisplay').textContent = formatCurrency(row.dataset.budgetAmount);
            document.getElementById('budgetDetailActualDisplay').textContent = formatCurrency(row.dataset.actualAmount);
            document.getElementById('budgetDetailRemainingDisplay').textContent = formatCurrency(row.dataset.remaining);
            document.getElementById('budgetDetailProjectDisplay').setAttribute('data-project-id', row.dataset.projectId);
            document.getElementById('budgetDetailProjectEdit').value = row.dataset.projectId;
            document.getElementById('budgetDetailAmountEdit').value = row.dataset.budgetAmount;

            selectedBudgetDetailFile = null;
            budgetFileMarkedForRemoval = false;
            var budgetDetailFileInput = document.getElementById('detailBudgetFile');
            // Run this step if the budget detail file input is set.
            if (budgetDetailFileInput) budgetDetailFileInput.value = '';
            var budFileNameDisplay = document.getElementById('budgetFileNameDisplay');
            var budFileIcon = document.getElementById('budgetFileIcon');
            var budViewButton = document.getElementById('viewBudgetFileBtn');
            var budDeleteButton = document.getElementById('deleteBudgetFileBtn');
            var budPreviewContainer = document.getElementById('budgetFilePreviewContainer');
            // Run this step if the bud preview container is set.
            if (budPreviewContainer) budPreviewContainer.style.display = 'none';
            // Run this step if the bud delete button is set.
            if (budDeleteButton) budDeleteButton.style.display = 'none';

            var existingBudgetProofPath = row.getAttribute('data-proof-file-path');
            var existingBudgetProofName = row.getAttribute('data-proof-file-name');
            // Run this step if the existing budget proof path is set.
            if (existingBudgetProofPath) {
                // Run this step if the bud file name display is set.
                if (budFileNameDisplay) budFileNameDisplay.textContent = existingBudgetProofName || existingBudgetProofPath;
                // Run this step if the bud file icon is set.
                if (budFileIcon) budFileIcon.textContent = existingBudgetProofPath.toLowerCase().endsWith('.pdf') ? 'PDF' : 'Image';
                // Run this step if the bud view button is set.
                if (budViewButton) {
                    budViewButton.style.display = 'inline-block';
                    budViewButton.setAttribute('data-file-url', '/storage/' + existingBudgetProofPath);
                }
            } else {
                // Run this step if the bud file name display is set.
                if (budFileNameDisplay) budFileNameDisplay.textContent = 'No file attached';
                // Run this step if the bud file icon is set.
                if (budFileIcon) budFileIcon.textContent = 'File';
                // Run this step if the bud view button is set.
                if (budViewButton) budViewButton.style.display = 'none';
            }

            // Run this step if the is budget edit mode is set.
            if (isBudgetEditMode) toggleBudgetDetailEdit();
            isBudgetEditMode = false;
            document.getElementById('budgetDetailModal').classList.remove('is-editing');
            document.getElementById('budgetDetailEditBtn').style.display = 'inline-block';
            document.getElementById('budgetDetailDeleteBtn').style.display = 'none';
            document.getElementById('budgetDetailSaveBtn').style.display = 'none';
            // Process each el.
            document.querySelectorAll('.budget-detail-edit').forEach(function(el) { el.style.display = 'none'; });
            // Process each el.
            document.querySelectorAll('.budget-detail-value').forEach(function(el) { el.style.display = ''; });

            document.getElementById('budgetDetailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Close budget detail modal.
        function closeBudgetDetailModal() {
            document.getElementById('budgetDetailModal').classList.remove('active');
            document.body.style.overflow = '';
            // Run this step if the is budget edit mode is set.
            if (isBudgetEditMode) toggleBudgetDetailEdit();
        }

        // Switch budget detail edit.
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

            // Run this step if the is budget edit mode is set.
            if (isBudgetEditMode) {
                document.getElementById('budgetDetailModal').classList.add('is-editing');
                document.getElementById('budgetDetailModalTitle').textContent = 'Edit Budget';
                editBtn.style.display = 'none';
                deleteBtn.style.display = 'inline-block';
                saveBtn.style.display = 'inline-block';
                // Run this step if the change file btn is set.
                if (changeFileBtn) changeFileBtn.style.display = 'inline-block';
                // Process each el.
                displayEls.forEach(function(el) { el.style.display = el.parentElement.querySelector('.budget-detail-edit') ? 'none' : ''; });
                // Process each el.
                editEls.forEach(function(el) { el.style.display = ''; });
                var hasFile = selectedBudgetDetailFile || (fileNameDisplay && fileNameDisplay.textContent !== 'No file attached');
                // Run this step if the delete file btn is set.
                if (deleteFileBtn) deleteFileBtn.style.display = hasFile ? 'inline-block' : 'none';
            } else {
                document.getElementById('budgetDetailModal').classList.remove('is-editing');
                document.getElementById('budgetDetailModalTitle').textContent = 'Budget Details';
                editBtn.style.display = 'inline-block';
                deleteBtn.style.display = 'none';
                saveBtn.style.display = 'none';
                // Run this step if the change file btn is set.
                if (changeFileBtn) changeFileBtn.style.display = 'none';
                // Run this step if the delete file btn is set.
                if (deleteFileBtn) deleteFileBtn.style.display = 'none';
                // Process each el.
                displayEls.forEach(function(el) { el.style.display = ''; });
                // Process each el.
                editEls.forEach(function(el) { el.style.display = 'none'; });
            }
        }

        // Save budget detail changes.
        function saveBudgetDetailChanges() {
            // Return early if the current budget row is missing or false.
            if (!currentBudgetRow) return;
            var originalProjectId = document.getElementById('budgetDetailProjectDisplay').getAttribute('data-project-id');
            var projectId = document.getElementById('budgetDetailProjectEdit').value;
            var budgetAmount = parseFloat(document.getElementById('budgetDetailAmountEdit').value) || 0;

            // Run this step if the project id is missing or false.
            if (!projectId) { showError('Project information is missing.'); return; }
            // Run this step if the budget amount is at most 0.
            if (budgetAmount <= 0) { showError('Budget amount must be greater than 0.'); return; }

            // Run the find action.
            var budget = budgetData.find(function(b) {
                return String(b.project_id) === String(originalProjectId);
            });

            var budgetDetailFormData = new FormData();
            budgetDetailFormData.append('_method', 'PUT');
            budgetDetailFormData.append('project_id', projectId);
            budgetDetailFormData.append('budget_amount', budgetAmount);
            // Run this step if the selected budget detail file is set.
            if (selectedBudgetDetailFile) {
                budgetDetailFormData.append('proof_file', selectedBudgetDetailFile);
            } else if (budgetFileMarkedForRemoval) {
                budgetDetailFormData.append('remove_proof_file', '1');
            }

            var url = budget && budget.budget_id ? '/budgets/' + budget.budget_id : '/budgets';

            // Use the result when this request finishes.
            fetch(API_BASE + url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: budgetDetailFormData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                // Return early if the response ok is missing or false.
                if (!response.ok) {
                    // Use the result when this request finishes.
                    return response.json().then(function(data) {
                        throw new Error(data.message || 'Unable to update budget.');
                    });
                }
                return response.json();
            })
            .then(function() {
                closeBudgetDetailModal();
                showSuccess('Budget updated successfully!');
                budgetDataCache = null;
                fetchBudgetData();
            })
            .catch(function(error) { showError(error.message); });
        }

        // Delete budget.
        function deleteBudget() {
            // Return early if the current budget row is missing or false.
            if (!currentBudgetRow) return;
            var projectId = currentBudgetRow.getAttribute('data-project-id');

            // Run the open budget delete modal action.
            openBudgetDeleteModal('Are you sure you want to move this budget to Trash Bin?', function() {
                // Run the find action.
                var budget = budgetData.find(function(b) {
                    return String(b.project_id) === String(projectId);
                });

                // Run this step if the budget is set and the budget id is set.
                if (budget && budget.budget_id) {
                    // Use the result when this request finishes.
                    apiFetch('/budgets/' + budget.budget_id, { method: 'DELETE' })
                        .then(function() {
                            closeBudgetDeleteModal();
                            showSuccess('Budget deleted successfully!');
                            currentBudgetRow = null;
                            budgetDataCache = null;
                            fetchBudgetData();
                        })
                        .catch(function(error) { showError(error.message); });
                } else {
                    // Keep the matching item items.
                    budgetData = budgetData.filter(function(item) {
                        return String(item.project_id) !== String(projectId);
                    });
                    filterBudgetTable();
                    closeBudgetDeleteModal();
                    updateFinanceTotals();
                    showSuccess('Budget removed from view.');
                    currentBudgetRow = null;
                }
            });
        }

        // Handle the click event.
        document.getElementById('budgetDetailModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) closeBudgetDetailModal();
        });

        // Expense detail modal
        function openExpenseModal(row) {
            currentDetailRow = row;
            var isPendingInventory = row.dataset.isPendingInventory === 'true';
            var isInventoryExpense = row.dataset.isInventoryExpense === 'true';
            var openInEditMode = window.PFIMS_ROW_EDIT_MODE === true && !isInventoryExpense;
            document.getElementById('detailModalTitle').textContent = isInventoryExpense ? 'Stock-In Expense Details' : 'Expense Details';
            document.getElementById('detailProjectDisplay').textContent = row.dataset.project || (row.dataset.entryKind === 'inventory_purchase' ? 'For Storage' : '—');
            // Run the find action.
            var detailCategory = financeCategories.find(function(item) { return String(item.fin_category_id || item.expense_category_id) === String(row.dataset.categoryId); });
            var detailType = expenseTypeForCategory(detailCategory) || 'direct';
            document.getElementById('detailExpenseTypeDisplay').textContent = detailType === 'office' ? 'Office Expenses' : (detailType === 'admin' ? 'Admin Expenses' : 'Direct Expenses');
            document.getElementById('detailExpenseTypeEdit').value = detailType;
            document.getElementById('detailDescDisplay').textContent = row.dataset.desc;
            document.getElementById('detailCategoryDisplay').textContent = row.dataset.category;
            document.getElementById('detailCostComponentDisplay').textContent = formatCostComponent(row.dataset.costComponent);
            document.getElementById('detailAmountDisplay').textContent = row.dataset.amount === '' ? '—' : formatCurrency(row.dataset.amount);
            document.getElementById('detailDateDisplay').textContent = row.dataset.date;
            document.getElementById('detailRemarksDisplay').textContent = row.dataset.remarks || '—';

            document.getElementById('detailProjectEdit').value = row.dataset.entryKind === 'office_expense' || detailType === 'admin' ? 'office' : (row.dataset.projectId || '');
            document.getElementById('detailDescEdit').value = row.dataset.desc;
            updateDetailExpenseType(row.dataset.categoryId || '');
            document.getElementById('detailAmountEdit').value = row.dataset.amount;
            document.getElementById('detailDateEdit').value = row.dataset.date;
            document.getElementById('detailRemarksEdit').value = row.dataset.remarks || '';

            selectedDetailFile = null;
            expenseFileMarkedForRemoval = false;
            var detailFileInput = document.getElementById('detailExpenseFile');
            // Run this step if the detail file input is set.
            if (detailFileInput) detailFileInput.value = '';
            var expFileNameDisplay = document.getElementById('expenseFileNameDisplay');
            var expFileIcon = document.getElementById('expenseFileIcon');
            var expViewButton = document.getElementById('viewExpenseFileBtn');
            var expDeleteButton = document.getElementById('deleteExpenseFileBtn');
            var expPreviewContainer = document.getElementById('filePreviewContainer');
            // Run this step if the exp preview container is set.
            if (expPreviewContainer) expPreviewContainer.style.display = 'none';
            // Run this step if the exp delete button is set.
            if (expDeleteButton) expDeleteButton.style.display = 'none';

            var existingExpenseProofPath = row.getAttribute('data-proof-file-path');
            var existingExpenseProofName = row.getAttribute('data-proof-file-name');
            // Run this step if the existing expense proof path is set.
            if (existingExpenseProofPath) {
                // Run this step if the exp file name display is set.
                if (expFileNameDisplay) expFileNameDisplay.textContent = existingExpenseProofName || existingExpenseProofPath;
                // Run this step if the exp file icon is set.
                if (expFileIcon) expFileIcon.textContent = existingExpenseProofPath.toLowerCase().endsWith('.pdf') ? 'PDF' : 'Image';
                // Run this step if the exp view button is set.
                if (expViewButton) {
                    expViewButton.style.display = 'inline-block';
                    expViewButton.setAttribute('data-file-url', '/storage/' + existingExpenseProofPath);
                }
            } else {
                // Run this step if the exp file name display is set.
                if (expFileNameDisplay) expFileNameDisplay.textContent = 'No file attached';
                // Run this step if the exp file icon is set.
                if (expFileIcon) expFileIcon.textContent = 'File';
                // Run this step if the exp view button is set.
                if (expViewButton) expViewButton.style.display = 'none';
            }

            // Run this step if the is edit mode is set and the is inventory expense is missing or false.
            if (isEditMode && !isInventoryExpense) toggleDetailEdit();
            isEditMode = false;
            document.getElementById('expenseDetailModal').classList.remove('is-editing');
            var canViewInventory = document.body.dataset.portal === 'admin';
            document.getElementById('detailViewInventoryBtn').style.display = isInventoryExpense && canViewInventory ? 'inline-block' : 'none';
            document.getElementById('detailEditBtn').style.display = isInventoryExpense && row.dataset.entryKind !== 'inventory_purchase' ? 'none' : 'inline-block';
            document.getElementById('detailDeleteBtn').style.display = 'none';
            document.getElementById('detailSaveBtn').style.display = 'none';
            // Process each el.
            document.querySelectorAll('.detail-edit').forEach(function(el) { el.style.display = 'none'; });
            // Process each el.
            document.querySelectorAll('.detail-value').forEach(function(el) { el.style.display = ''; });

            document.getElementById('expenseDetailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            // Run this step if the open in edit mode is set.
            if (openInEditMode) {
                // Run this action after the delay.
                window.setTimeout(function() {
                    // Run this step if the is edit mode is missing or false and the current detail row equals the row.
                    if (!isEditMode && currentDetailRow === row) toggleDetailEdit();
                }, 0);
            }
        }

        // Close expense detail modal.
        function closeExpenseDetailModal() {
            document.getElementById('expenseDetailModal').classList.remove('active');
            document.body.style.overflow = '';
            // Run this step if the is edit mode is set.
            if (isEditMode) toggleDetailEdit();
        }

        // Switch detail edit.
        function toggleDetailEdit() {
            // Run this step if the current detail row is set and the is inventory expense equals true.
            if (currentDetailRow && currentDetailRow.dataset.isInventoryExpense === 'true') {
                var transactionId = currentDetailRow.dataset.inventoryTransactionId;
                closeExpenseDetailModal();
                openInventoryExpenseModal(transactionId);
                return;
            }
            isEditMode = !isEditMode;
            var displayEls = document.querySelectorAll('.detail-value');
            var editEls = document.querySelectorAll('.detail-edit');
            var editBtn = document.getElementById('detailEditBtn');
            var deleteBtn = document.getElementById('detailDeleteBtn');
            var saveBtn = document.getElementById('detailSaveBtn');
            var changeFileBtn = document.getElementById('changeExpenseFileBtn');
            var deleteFileBtn = document.getElementById('deleteExpenseFileBtn');
            var fileNameDisplay = document.getElementById('expenseFileNameDisplay');

            // Run this step if the is edit mode is set.
            if (isEditMode) {
                document.getElementById('expenseDetailModal').classList.add('is-editing');
                document.getElementById('detailModalTitle').textContent = 'Edit Expense';
                editBtn.style.display = 'none';
                deleteBtn.style.display = 'inline-block';
                saveBtn.style.display = 'inline-block';
                // Run this step if the change file btn is set.
                if (changeFileBtn) changeFileBtn.style.display = 'inline-block';
                // Process each el.
                displayEls.forEach(function(el) { el.style.display = 'none'; });
                // Process each el.
                editEls.forEach(function(el) { el.style.display = ''; });
                document.getElementById('detailComponentGroup').style.display = 'none';
                updateDetailExpenseCategory();
                var hasFile = selectedDetailFile || (fileNameDisplay && fileNameDisplay.textContent !== 'No file attached');
                // Run this step if the delete file btn is set.
                if (deleteFileBtn) deleteFileBtn.style.display = hasFile ? 'inline-block' : 'none';
            } else {
                document.getElementById('expenseDetailModal').classList.remove('is-editing');
                document.getElementById('detailModalTitle').textContent = 'Expense Details';
                editBtn.style.display = 'inline-block';
                document.getElementById('detailComponentGroup').style.display = '';
                deleteBtn.style.display = 'none';
                saveBtn.style.display = 'none';
                // Run this step if the change file btn is set.
                if (changeFileBtn) changeFileBtn.style.display = 'none';
                // Run this step if the delete file btn is set.
                if (deleteFileBtn) deleteFileBtn.style.display = 'none';
                // Process each el.
                displayEls.forEach(function(el) { el.style.display = ''; });
                // Process each el.
                editEls.forEach(function(el) { el.style.display = 'none'; });
            }
        }

        // Open linked inventory transaction.
        function openLinkedInventoryTransaction(event) {
            // Run this step if the event is set.
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            // Run this step if the current detail row is missing or false or the inventory transaction id is missing or false.
            if (!currentDetailRow || !currentDetailRow.dataset.inventoryTransactionId) {
                showError('Linked inventory transaction is unavailable.');
                return;
            }
            var transactionId = currentDetailRow.dataset.inventoryTransactionId;
            document.getElementById('detailDeleteBtn').style.display = 'none';
            // Try this action and handle any errors below.
            try {
                window.sessionStorage.setItem('pfims_inventory_transaction_to_open', transactionId);
            } catch (storageError) {
                console.warn('Unable to preserve the linked inventory transaction handoff.', storageError);
            }
            window.pfimsNavigate('/inventory?section=transactions&transaction_id=' + encodeURIComponent(transactionId));
        }

        // Save detail changes.
        function saveDetailChanges() {
            // Return early if the current detail row is missing or false.
            if (!currentDetailRow) return;
            var projectId = document.getElementById('detailProjectEdit').value;
            var desc = document.getElementById('detailDescEdit').value.trim();
            var categoryId = document.getElementById('detailCategoryEdit').value;
            var amount = parseFloat(document.getElementById('detailAmountEdit').value) || 0;
            var date = document.getElementById('detailDateEdit').value;
            var remarks = document.getElementById('detailRemarksEdit').value.trim();

            // Run this step if the value checks match.
            if (!desc || !categoryId || !amount || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            // Run this step if the amount is at most 0.
            if (amount <= 0) { showError('Amount must be greater than 0.'); return; }

            // Run the find action.
            var detailCategory = financeCategories.find(function(c) {
                return c.fin_category_id == categoryId || c.expense_category_id == categoryId;
            });
            var isDirectDetailCategory = detailCategory && String(detailCategory.classification || '').toLowerCase() === 'direct';
            // Run this step if the detail category is set and the includes check matches.
            if (detailCategory && ['admin', 'office'].includes(String(detailCategory.classification || '').toLowerCase())) projectId = 'office';
            // Run this step if the is direct detail category is set and the project id is missing or false.
            if (isDirectDetailCategory && !projectId) {
                showError('Direct project expenses require a project.');
                return;
            }

            var expenseId = currentDetailRow.getAttribute('data-expense-id');

            var detailFormData = new FormData();
            detailFormData.append('_method', 'PUT');
            detailFormData.append('project_id', projectId === 'office' ? '' : projectId);
            // Run this step if the project id equals office.
            if (projectId === 'office') detailFormData.append('office_expense', '1');
            detailFormData.append('fin_category_id', categoryId);
            detailFormData.append('expense_description', desc);
            detailFormData.append('amount', amount);
            detailFormData.append('expense_date', date);
            // Run this step if the remarks is set.
            if (remarks) detailFormData.append('remarks', remarks);
            // Run this step if the selected detail file is set.
            if (selectedDetailFile) {
                detailFormData.append('proof_file', selectedDetailFile);
            } else if (expenseFileMarkedForRemoval) {
                detailFormData.append('remove_proof_file', '1');
            }

            // Use the result when this request finishes.
            fetch(API_BASE + '/finance-expenses/' + expenseId, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: detailFormData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                // Return early if the response ok is missing or false.
                if (!response.ok) {
                    // Use the result when this request finishes.
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

        // Delete expense.
        function deleteExpense() {
            // Return early if the current detail row is missing or false.
            if (!currentDetailRow) return;
            // Run the open delete modal action.
            openDeleteModal('Are you sure you want to move this expense to Trash Bin?', function() {
                var expenseId = currentDetailRow.getAttribute('data-expense-id');
                // Use the result when this request finishes.
                apiFetch('/finance-expenses/' + expenseId, { method: 'DELETE' })
                    .then(function() {
                        closeExpenseDetailModal();
                        fetchExpenses();
                        showSuccess('Expense deleted successfully!');
                        currentDetailRow = null;
                    })
                    .catch(function(error) { showError(error.message); });
            });
        }

        // Add expense (unified - handles all expense types)
        function openAddExpenseModal() {
            document.getElementById('addExpenseModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('expenseType').value = 'direct';
            updateExpenseType();
            document.getElementById('expenseDesc').value = '';
            document.getElementById('expenseCategory').value = '';
            document.getElementById('expenseAmount').value = '';
            document.getElementById('expenseDate').value = '{{ date("Y-m-d") }}';
            document.getElementById('expenseRemarks').value = '';
            document.getElementById('addExpenseModalTitle').textContent = 'Add Expense';

            var expenseProofFileInput = document.getElementById('expenseProofFile');
            var expenseProofFileNameEl = document.getElementById('expenseProofFileName');
            // Run this step if the expense proof file input is set.
            if (expenseProofFileInput) expenseProofFileInput.value = '';
            // Run this step if the expense proof file name el is set.
            if (expenseProofFileNameEl) expenseProofFileNameEl.textContent = 'No file chosen';
        }

        // Close add expense modal.
        function closeAddExpenseModal() {
            document.getElementById('addExpenseModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Save expense.
        function saveExpense() {
            var type = document.getElementById('expenseType').value;
            var projectChoice = document.getElementById('expenseProject').value;
            var projectId = projectChoice === 'office' ? '' : projectChoice;
            var desc = document.getElementById('expenseDesc').value.trim();
            var categoryId = document.getElementById('expenseCategory').value;
            // Run the find action.
            var selectedExpenseCategory = financeCategories.find(function(c) { return c.fin_category_id == categoryId || c.expense_category_id == categoryId; });
            var date = document.getElementById('expenseDate').value;
            var remarks = document.getElementById('expenseRemarks').value.trim();
            var amount = Number(document.getElementById('expenseAmount').value);

            // Run this step if the value checks match.
            if (!categoryId || !projectChoice || !date || !desc || !Number.isFinite(amount) || amount <= 0) {
                showError('Please fill in all required fields.');
                return;
            }
            // Run this step if the value checks match.
            if (!selectedExpenseCategory || expenseTypeForCategory(selectedExpenseCategory) !== type ||
                (type === 'admin' && projectChoice !== 'office')) {
                showError('Invalid category selected.');
                return;
            }
            // Run this step if the project id is set and the some is missing or false.
            if (projectId && !(budgetDataCache || budgetData).some(function(budget) {
                return String(budget.project_id) === String(projectId) && Number(budget.budget_amount) > 0;
            })) {
                showError('Add a budget for this project before recording an expense.');
                return;
            }

            var expenseFormData = new FormData();
            expenseFormData.append('project_id', projectId);
            // Run this step if the project choice equals office.
            if (projectChoice === 'office') expenseFormData.append('office_expense', '1');
            expenseFormData.append('fin_category_id', categoryId);
            expenseFormData.append('expense_description', desc);
            expenseFormData.append('expense_date', date);
            // Run this step if the remarks is set.
            if (remarks) expenseFormData.append('remarks', remarks);
            expenseFormData.append('amount', amount);

            var expenseProofFileInput = document.getElementById('expenseProofFile');
            // Run this step if the expense proof file input is set and the files is set and the files length is above 0.
            if (expenseProofFileInput && expenseProofFileInput.files && expenseProofFileInput.files.length > 0) {
                expenseFormData.append('proof_file', expenseProofFileInput.files[0]);
            }

            var savedType = type;

            // Use the result when this request finishes.
            fetch(API_BASE + '/finance-expenses', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: expenseFormData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                // Return early if the response ok is missing or false.
                if (!response.ok) {
                    // Use the result when this request finishes.
                    return response.json().then(function(data) {
                        var projectErrors = data.errors && data.errors.project_id;
                        throw new Error((projectErrors && projectErrors[0]) || data.message || data.error || 'Unable to save expense.');
                    });
                }
                return response.json();
            })
            .then(function() {
                closeAddExpenseModal();
                showSuccess((savedType === 'admin' ? 'Admin ' : (savedType === 'office' ? 'Office ' : '')) + 'Expense added successfully!');
                fetchExpenses();
                fetchBudgetData();
                // Refresh all report views
                loadExpovrall();
                loadExpDirect();
                loadAdminExp();
                loadDirectExp();
                loadOverallExp();
            })
            .catch(function(error) { showError(error.message); });
        }

        // Add budget
        function openAddBudgetModal() {
            document.getElementById('addBudgetModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('budgetProject').value = '';
            document.getElementById('budgetAmount').value = '';

            var budgetProofFileInput = document.getElementById('budgetProofFile');
            var budgetProofFileNameEl = document.getElementById('budgetProofFileName');
            // Run this step if the budget proof file input is set.
            if (budgetProofFileInput) budgetProofFileInput.value = '';
            // Run this step if the budget proof file name el is set.
            if (budgetProofFileNameEl) budgetProofFileNameEl.textContent = 'No file chosen';
        }

        // Close add budget modal.
        function closeAddBudgetModal() {
            document.getElementById('addBudgetModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Save budget.
        function saveBudget() {
            var projectId = document.getElementById('budgetProject').value;
            var amount = document.getElementById('budgetAmount').value;

            // Run this step if the project id is missing or false or the amount is missing or false or the parse float is at most 0.
            if (!projectId || !amount || parseFloat(amount) <= 0) {
                showError('Please select a project and enter a valid budget amount.');
                return;
            }

            var budgetFormData = new FormData();
            budgetFormData.append('project_id', projectId);
            budgetFormData.append('budget_amount', amount);
            var budgetProofFileInput = document.getElementById('budgetProofFile');
            // Run this step if the budget proof file input is set and the files is set and the files length is above 0.
            if (budgetProofFileInput && budgetProofFileInput.files && budgetProofFileInput.files.length > 0) {
                budgetFormData.append('proof_file', budgetProofFileInput.files[0]);
            }

            // Use the result when this request finishes.
            fetch(API_BASE + '/budgets', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: budgetFormData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                // Return early if the response ok is missing or false.
                if (!response.ok) {
                    // Use the result when this request finishes.
                    return response.json().then(function(data) {
                        throw new Error(data.message || 'Unable to save budget.');
                    });
                }
                return response.json();
            })
            .then(function() {
                closeAddBudgetModal();
                showSuccess('Budget added successfully!');
                budgetDataCache = null;
                fetchBudgetData();
            })
            .catch(function(error) { showError(error.message); });
        }

        // Add contract
        function openAddContractModal(row) {
            document.getElementById('addContractModal').classList.remove('is-viewing');
            // Process each el.
            document.querySelectorAll('#addContractModal .contract-detail-value').forEach(function(el) { el.remove(); });
            // Process each el.
            document.querySelectorAll('#addContractModal input, #addContractModal select').forEach(function(el) { el.disabled = false; });
            document.getElementById('addContractModal').classList.add('active');
            document.getElementById('addContractModal').scrollTop = 0;
            document.querySelector('#addContractModal .modal-container').scrollTop = 0;
            document.body.style.overflow = 'hidden';
            document.getElementById('addContractModal').classList.remove('is-editing');
            // Populate project dropdown
            if (!row) populateContractProjectDropdown().catch(function(error) { showError(error.message); });
            
            var projectSelect = document.getElementById('contractProject');
            var projectDisplay = document.getElementById('contractProjectDisplay');
            
            // Run this step if the row is set.
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
                
                document.getElementById('contractSaveBtn').textContent = 'Save Contract';
                
                updateContractBudgetDisplay();
            }
        }

        // Open contract view modal.
        function openContractViewModal(row) {
            openAddContractModal(row);
            document.getElementById('addContractModal').classList.add('is-viewing');
            // Process each control.
            document.querySelectorAll('#addContractModal input').forEach(function(control) {
                var value = document.createElement('span');
                value.className = 'contract-detail-value detail-value';
                value.textContent = control.type === 'number' ? formatCurrency(control.value || 0) : (control.value || '—');
                control.closest('.form-group').appendChild(value);
            });
            document.getElementById('addContractModal').classList.remove('is-editing');
            document.getElementById('contractModalTitle').textContent = 'Contract Details';
            // Process each control.
            document.querySelectorAll('#addContractModal input, #addContractModal select, #addContractModal textarea').forEach(function(control) {
                control.disabled = true;
            });
            document.getElementById('contractEditBtn').style.display = 'inline-block';
            document.getElementById('contractDeleteBtn').style.display = 'inline-block';
            document.querySelector('#addContractModal .btn-save').style.display = 'none';
        }

        // Get the enable contract edit.
        function enableContractEdit() {
            document.getElementById('addContractModal').classList.remove('is-viewing');
            // Process each el.
            document.querySelectorAll('#addContractModal .contract-detail-value').forEach(function(el) { el.remove(); });
            document.getElementById('addContractModal').classList.add('is-editing');
            document.getElementById('contractModalTitle').textContent = 'Edit Contract';
            // Process each control.
            document.querySelectorAll('#addContractModal input, #addContractModal select, #addContractModal textarea').forEach(function(control) {
                control.disabled = false;
            });
            document.getElementById('contractProject').disabled = true;
            document.getElementById('contractEditBtn').style.display = 'none';
            document.getElementById('contractDeleteBtn').style.display = 'none';
            document.getElementById('contractSaveBtn').textContent = 'Save Changes';
            document.getElementById('contractSaveBtn').style.display = 'inline-block';
        }

        // Get the populate contract project dropdown.
        function populateContractProjectDropdown() {
            var select = document.getElementById('contractProject');
            var currentValue = select.value;
            select.innerHTML = '<option value="">Loading projects...</option>';
            // Use the result when this request finishes.
            return fetchProjects().then(function() {
                select.innerHTML = '<option value="">Select Project...</option>';
                // Process each project.
                financeProjects.forEach(function(project) {
                    var option = document.createElement('option');
                    option.value = project.project_id;
                    option.textContent = project.project_name;
                    option.setAttribute('data-budget', project.budget || 0);
                    select.appendChild(option);
                });
                // Run this step if the current value is set.
                if (currentValue) select.value = currentValue;
                updateContractBudgetDisplay();
            });
        }

        // Update contract budget display.
        function updateContractBudgetDisplay() {
            var select = document.getElementById('contractProject');
            var selectedOption = select.options[select.selectedIndex];
            var budget = selectedOption ? parseFloat(selectedOption.getAttribute('data-budget')) || 0 : 0;
            document.getElementById('contractBudgetDisplay').textContent = formatCurrency(budget);
            // Run this step if the select value is set and the budget is at most 0.
            if (select.value && budget <= 0) showError('Add a budget for this project before recording a contract.');
        }

        // Update contract budget display for project.
        function updateContractBudgetDisplayForProject(projectId) {
            // Run the find action.
            var project = financeProjects.find(function(p) {
                return p.project_id == projectId;
            });
            var budget = project ? parseFloat(project.budget) || 0 : 0;
            document.getElementById('contractBudgetDisplay').textContent = formatCurrency(budget);
        }

        // Close add contract modal.
        function closeAddContractModal() {
            document.getElementById('addContractModal').classList.remove('active');
            document.body.style.overflow = '';
            // Reset to add mode
            document.getElementById('contractProject').style.display = 'block';
            document.getElementById('contractProjectDisplay').style.display = 'none';
            // Process each control.
            document.querySelectorAll('#addContractModal input, #addContractModal select, #addContractModal textarea').forEach(function(control) { control.disabled = false; });
            document.getElementById('contractEditBtn').style.display = 'none';
            document.getElementById('contractDeleteBtn').style.display = 'none';
            document.querySelector('#addContractModal .btn-save').style.display = 'inline-block';
        }

        // Save contract.
        function saveContract() {
            var editId = document.getElementById('addContractModal').getAttribute('data-edit-id');
            var projectId;
            var contractPrice;
            
            // Run this step if the edit id is set.
            if (editId) {
                // EDIT MODE - Get project ID from data attribute
                projectId = document.getElementById('addContractModal').getAttribute('data-project-id');
                // Run this step if the project id is missing or false.
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

            // Run this step if the project id is missing or false.
            if (!projectId) {
                showError('Please select a project.');
                return;
            }

            // Run this step if the contract price is at most 0.
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

            // Use the result when this request finishes.
            apiFetch(endpoint, { method: method, body: JSON.stringify(payload) })
                .then(function() {
                    closeAddContractModal();
                    showSuccess('Contract ' + (editId ? 'updated' : 'added') + ' successfully!');
                    document.getElementById('profitYear').value = '';
                    loadProfit();
                })
                .catch(function(error) { showError(error.message); });
        }

        // Delete contract.
        function deleteContract() {
            var editId = document.getElementById('addContractModal').getAttribute('data-edit-id');
            // Run this step if the edit id is missing or false.
            if (!editId) {
                showError('No contract to delete.');
                return;
            }
            
            // Run the open delete modal action.
            openDeleteModal('Are you sure you want to move this contract to Trash Bin?', function() {
                // Use the result when this request finishes.
                apiFetch('/project-contracts/' + editId, { method: 'DELETE' })
                    .then(function() {
                        closeAddContractModal();
                        showSuccess('Contract deleted successfully!');
                        loadProfit();
                    })
                    .catch(function(error) { showError(error.message); });
            });
        }

        // Add receivable
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

        // Close add receivable modal.
        function closeAddReceivableModal() {
            document.getElementById('addReceivableModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Save receivable.
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

            // Run this step if the entry type is missing or false or the counterparty is missing or false or the date is missing or false.
            if (!entryType || !counterparty || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            // Run this step if the amt30d equals 0 and the amt60d equals 0 and the amt90d equals 0 and the amt120d equals 0.
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

            // Use the result when this request finishes.
            apiFetch('/receivables-payables', { method: 'POST', body: JSON.stringify(payload) })
                .then(function() {
                    closeAddReceivableModal();
                    showSuccess('Entry added successfully!');
                    loadReceivables();
                })
                .catch(function(error) { showError(error.message); });
        }

        // Receivable detail modal
        var currentReceivableRow = null;
        var isReceivableEditMode = false;

        // Open receivable modal.
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

            // Run this step if the is receivable edit mode is set.
            if (isReceivableEditMode) toggleReceivableEdit();
            isReceivableEditMode = false;
            document.getElementById('receivableDetailModal').classList.remove('is-editing');
            document.getElementById('receivableDetailEditBtn').style.display = 'inline-block';
            document.getElementById('receivableDetailDeleteBtn').style.display = 'none';
            document.getElementById('receivableDetailSaveBtn').style.display = 'none';
            
            // Reset display/edit visibility
            document.querySelectorAll('#receivableDetailModal .detail-edit').forEach(function(el) { 
                el.style.display = 'none'; 
            });
            // Process each el.
            document.querySelectorAll('#receivableDetailModal .detail-value').forEach(function(el) { 
                el.style.display = ''; 
            });
            // Hide the display-only counterparty and project spans initially
            document.getElementById('receivableDetailCounterpartyDisplay').style.display = 'none';
            document.getElementById('receivableDetailProjectDisplay').style.display = 'none';

            document.getElementById('receivableDetailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Format entry type.
        function formatEntryType(type) {
            var map = {
                'accounts_receivable': 'Accounts Receivable',
                'accounts_payable': 'Accounts Payable',
                'cash_advance_site': 'Cash Advance (Site)',
                'advance_employee': 'Advances to Employees'
            };
            return map[type] || type;
        }

        // Close receivable modal.
        function closeReceivableModal() {
            document.getElementById('receivableDetailModal').classList.remove('active');
            document.body.style.overflow = '';
            // Run this step if the is receivable edit mode is set.
            if (isReceivableEditMode) toggleReceivableEdit();
        }

        // Switch receivable edit.
        function toggleReceivableEdit() {
            isReceivableEditMode = !isReceivableEditMode;
            var editBtn = document.getElementById('receivableDetailEditBtn');
            var deleteBtn = document.getElementById('receivableDetailDeleteBtn');
            var saveBtn = document.getElementById('receivableDetailSaveBtn');
            
            var editableFields = ['receivableDetailTypeEdit', 'receivableDetailCounterpartyEdit', 'receivableDetailProjectEdit',
                                  'receivableDetailDateEdit', 'receivableDetail30dEdit', 'receivableDetail60dEdit',
                                  'receivableDetail90dEdit', 'receivableDetail120dEdit', 'receivableDetailStatusEdit', 
                                  'receivableDetailRemarksEdit'];
            
            var displayFields = ['receivableDetailType', 'receivableDetailCounterparty', 'receivableDetailProject',
                                 'receivableDetailDate', 'receivableDetail30d', 'receivableDetail60d', 
                                 'receivableDetail90d', 'receivableDetail120d', 'receivableDetailStatus', 
                                 'receivableDetailRemarks'];

            // Run this step if the is receivable edit mode is set.
            if (isReceivableEditMode) {
                document.getElementById('receivableDetailModal').classList.add('is-editing');
                document.getElementById('receivableModalTitle').textContent = 'Edit AR/AP Entry';
                editBtn.style.display = 'none';
                deleteBtn.style.display = 'inline-block';
                saveBtn.style.display = 'inline-block';
                
                // Hide display values
                displayFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    // Run this step if the el is set.
                    if (el) el.style.display = 'none';
                });
                
                // Process each id.
                editableFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    // Run this step if the el is set.
                    if (el) el.style.display = '';
                });
                document.getElementById('receivableDetailCounterpartyDisplay').style.display = 'none';
                document.getElementById('receivableDetailProjectDisplay').style.display = 'none';
                
            } else {
                document.getElementById('receivableDetailModal').classList.remove('is-editing');
                document.getElementById('receivableModalTitle').textContent = 'AR/AP Entry Details';
                editBtn.style.display = 'inline-block';
                deleteBtn.style.display = 'none';
                saveBtn.style.display = 'none';
                
                // Show display values
                displayFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    // Run this step if the el is set.
                    if (el) el.style.display = '';
                });
                
                // Hide edit fields
                editableFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    // Run this step if the el is set.
                    if (el) el.style.display = 'none';
                });
                
                // Hide the read-only display spans
                document.getElementById('receivableDetailCounterpartyDisplay').style.display = 'none';
                document.getElementById('receivableDetailProjectDisplay').style.display = 'none';
            }
        }

        // Save receivable detail.
        function saveReceivableDetail() {
            // Return early if the current receivable row is missing or false.
            if (!currentReceivableRow) return;
            var rpId = currentReceivableRow.getAttribute('data-rp-id');

            var payload = {
                entry_type: document.getElementById('receivableDetailTypeEdit').value,
                project_id: document.getElementById('receivableDetailProjectEdit').value || null,
                counterparty_name: document.getElementById('receivableDetailCounterpartyEdit').value.trim(),
                entry_date: document.getElementById('receivableDetailDateEdit').value,
                amount_30d: parseFloat(document.getElementById('receivableDetail30dEdit').value) || 0,
                amount_31_60d: parseFloat(document.getElementById('receivableDetail60dEdit').value) || 0,
                amount_61_90d: parseFloat(document.getElementById('receivableDetail90dEdit').value) || 0,
                amount_91_120d: parseFloat(document.getElementById('receivableDetail120dEdit').value) || 0,
                status: document.getElementById('receivableDetailStatusEdit').value,
                remarks: document.getElementById('receivableDetailRemarksEdit').value.trim() || null
            };

            // Run this step if the value checks match.
            if (!payload.entry_type || !payload.counterparty_name || !payload.entry_date) {
                showError('Please fill in all required fields.');
                return;
            }

            // Use the result when this request finishes.
            apiFetch('/receivables-payables/' + rpId, { method: 'PUT', body: JSON.stringify(payload) })
                .then(function() {
                    closeReceivableModal();
                    showSuccess('Entry updated successfully!');
                    loadReceivables();
                })
                .catch(function(error) { showError(error.message); });
        }

        // Delete receivable.
        function deleteReceivable() {
            // Return early if the current receivable row is missing or false.
            if (!currentReceivableRow) return;
            var rpId = currentReceivableRow.getAttribute('data-rp-id');
            // Run the open delete modal action.
            openDeleteModal('Are you sure you want to move this entry to Trash Bin?', function() {
                // Use the result when this request finishes.
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

        // Handle the click event.
        document.getElementById('receivableDetailModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) closeReceivableModal();
        });

        // Add cash asset
        function openAddCashModal(selectedAccountId) {
            document.getElementById('addCashModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('cashPeriod').value = '{{ date("Y-m") }}';
            document.getElementById('cashBalance').value = '';
            document.getElementById('cashRemarks').value = '';
            // Use the result when this request finishes.
            return fetchCashAccounts().then(function() {
                // Run this step if the selected account id is set.
                if (selectedAccountId) document.getElementById('cashAccount').value = String(selectedAccountId);
            }).catch(function(error) {
                showError(error.message || 'Unable to load cash accounts.');
            });
        }

        // Close add cash modal.
        function closeAddCashModal() {
            document.getElementById('addCashModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Save cash position.
        function saveCashPosition() {
            var accountId = document.getElementById('cashAccount').value;
            var period = document.getElementById('cashPeriod').value;
            var balance = parseFloat(document.getElementById('cashBalance').value) || 0;
            var remarks = document.getElementById('cashRemarks').value.trim();

            // Run this step if the account id is missing or false or the period is missing or false or the balance is below 0.
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

            // Use the result when this request finishes.
            apiFetch('/cash-positions', { method: 'POST', body: JSON.stringify(payload) })
                .then(function() {
                    closeAddCashModal();
                    showSuccess('Cash Asset added successfully!');
                    loadCashAsset();
                })
                .catch(function(error) { showError(error.message); });
        }

        // Add repair
        function openAddRepairModal() {
            document.getElementById('addRepairModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('repairAssetSelect').value = '';
            document.getElementById('repairExpenseType').value = 'repair';
            document.getElementById('repairAmount').value = '';
            document.getElementById('repairDate').value = '{{ date("Y-m-d") }}';
            document.getElementById('repairRemarks').value = '';
        }

        // Close add repair modal.
        function closeAddRepairModal() {
            document.getElementById('addRepairModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Save repair.
        function saveRepair() {
            var assetId = document.getElementById('repairAssetSelect').value;
            var expenseType = document.getElementById('repairExpenseType').value;
            var amount = parseFloat(document.getElementById('repairAmount').value) || 0;
            var date = document.getElementById('repairDate').value;
            var remarks = document.getElementById('repairRemarks').value.trim();

            // Run this step if the value checks match.
            if (!assetId || !expenseType || !amount || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            // Run this step if the amount is at most 0.
            if (amount <= 0) { showError('Amount must be greater than 0.'); return; }

            var payload = {
                asset_id: parseInt(assetId),
                project_id: null,
                expense_type: expenseType,
                amount: amount,
                expense_date: date,
                remarks: remarks || null
            };

            // Use the result when this request finishes.
            apiFetch('/equipment-expenses', { method: 'POST', body: JSON.stringify(payload) })
                .then(function() {
                    closeAddRepairModal();
                    showSuccess('Repair added successfully!');
                    loadRepair();
                })
                .catch(function(error) { showError(error.message); });
        }

        // Add backhoe expense
        function openAddBackhoeExpenseModal() {
            document.getElementById('backhoeExpenseProject').required = false;
            document.querySelector('#backhoeExpenseProject').closest('.form-group').querySelector('label').textContent = 'Project Site';
            equipmentEditState = { type: null, id: null };
            document.getElementById('backhoeExpenseModalTitle').textContent = 'Add Backhoe Expense';
            document.getElementById('backhoeExpenseSaveBtn').textContent = 'Add Backhoe Expense';
            document.getElementById('addBackhoeExpenseModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('backhoeExpenseAsset').value = '';
            document.getElementById('backhoeExpenseProject').value = '';
            document.getElementById('backhoeExpenseType').value = 'gas_diesel';
            document.getElementById('backhoeExpenseAmount').value = '';
            document.getElementById('backhoeExpenseDate').value = '{{ date("Y-m-d") }}';
            document.getElementById('backhoeExpenseRemarks').value = '';
        }

        // Close add backhoe expense modal.
        function closeAddBackhoeExpenseModal() {
            document.getElementById('addBackhoeExpenseModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Save backhoe expense.
        function saveBackhoeExpense() {
            var assetId = document.getElementById('backhoeExpenseAsset').value;
            var projectId = document.getElementById('backhoeExpenseProject').value;
            var expenseType = document.getElementById('backhoeExpenseType').value;
            var amount = parseFloat(document.getElementById('backhoeExpenseAmount').value) || 0;
            var date = document.getElementById('backhoeExpenseDate').value;
            var remarks = document.getElementById('backhoeExpenseRemarks').value.trim();

            // Run this step if the value checks match.
            if (!assetId || !expenseType || !amount || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            // Run this step if the amount is at most 0.
            if (amount <= 0) { showError('Amount must be greater than 0.'); return; }

            var payload = {
                asset_id: parseInt(assetId),
                project_id: projectId || null,
                expense_type: expenseType,
                amount: amount,
                expense_date: date,
                remarks: remarks || null
            };

            var editId = equipmentEditState.type === 'expense' ? equipmentEditState.id : null;
            // Run this step if the edit id is set and the project id is missing or false.
            if (editId && !projectId) { showError('Project site is required when editing.'); return; }
            // Run this step if the edit id is set and the substring does not equal the 1.
            if (editId && date.substring(0, 7) !== currentEquipmentPeriodKey.split('|')[1]) { showError('The expense date does not match the rental period.'); return; }
            // Use the result when this request finishes.
            apiFetch(editId ? '/equipment-expenses/' + editId : '/equipment-expenses', { method: editId ? 'PUT' : 'POST', body: JSON.stringify(payload) })
                .then(function() {
                    closeAddBackhoeExpenseModal();
                    equipmentEditState = { type: null, id: null };
                    showSuccess(editId ? 'Equipment expense updated successfully!' : 'Backhoe expense added successfully!');
                    loadBackhoe();
                })
                .catch(function(error) { showError(error.message); });
        }

        // Add backhoe rental
        function openAddBackhoeRentalModal() {
            document.getElementById('backhoeRentalProject').required = false;
            document.querySelector('#backhoeRentalProject').closest('.form-group').querySelector('label').textContent = 'Project Site';
            equipmentEditState = { type: null, id: null };
            document.getElementById('backhoeRentalModalTitle').textContent = 'Add Backhoe Rental Income';
            document.getElementById('backhoeRentalSaveBtn').textContent = 'Add Rental Income';
            document.getElementById('addBackhoeRentalModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('backhoeRentalAsset').value = '';
            document.getElementById('backhoeRentalProject').value = '';
            document.getElementById('backhoeRentalPeriod').value = '{{ date("Y-m") }}';
            document.getElementById('backhoeRentalAmount').value = '';
            document.getElementById('backhoeRentalRemarks').value = '';
        }

        // Close add backhoe rental modal.
        function closeAddBackhoeRentalModal() {
            document.getElementById('addBackhoeRentalModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Save backhoe rental.
        function saveBackhoeRental() {
            var assetId = document.getElementById('backhoeRentalAsset').value;
            var projectId = document.getElementById('backhoeRentalProject').value;
            var period = document.getElementById('backhoeRentalPeriod').value;
            var amount = parseFloat(document.getElementById('backhoeRentalAmount').value) || 0;
            var remarks = document.getElementById('backhoeRentalRemarks').value.trim();

            // Run this step if the asset id is missing or false or the period is missing or false or the amount is at most 0.
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

            var editId = equipmentEditState.type === 'rental' ? equipmentEditState.id : null;
            // Run this step if the edit id is set and the project id is missing or false.
            if (editId && !projectId) { showError('Project site is required when editing.'); return; }
            // Use the result when this request finishes.
            apiFetch(editId ? '/equipment-rental-income/' + editId : '/equipment-rental-income', { method: editId ? 'PUT' : 'POST', body: JSON.stringify(payload) })
                .then(function() {
                    closeAddBackhoeRentalModal();
                    equipmentEditState = { type: null, id: null };
                    showSuccess(editId ? 'Rental income updated successfully!' : 'Rental income added successfully!');
                    loadBackhoe();
                })
                .catch(function(error) { showError(error.message); });
        }

        // Add bond
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

        // Close add bond modal.
        function closeAddBondModal() {
            document.getElementById('addBondModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Save bond.
        function saveBond() {
            var projectId = document.getElementById('bondProject').value;
            var date = document.getElementById('bondDate').value;
            var amount = parseFloat(document.getElementById('bondAmount').value) || 0;
            var provider = document.getElementById('bondProvider').value.trim();
            var status = document.getElementById('bondStatus').value;
            var remarks = document.getElementById('bondRemarks').value.trim();

            // Run this step if the project id is missing or false or the date is missing or false or the amount is at most 0.
            if (!projectId || !date || amount <= 0) {
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

            // Use the result when this request finishes.
            apiFetch('/construction-bonds', { method: 'POST', body: JSON.stringify(payload) })
                .then(function() {
                    closeAddBondModal();
                    showSuccess('Bond added successfully!');
                    loadBonds();
                })
                .catch(function(error) { showError(error.message); });
        }

        // Save bond detail.
        function saveBondDetail() {
            // Return early if the current bond row is missing or false.
            if (!currentBondRow) return;
            var bondId = currentBondRow.getAttribute('data-bond-id');

            var payload = {
                project_id: parseInt(document.getElementById('bondDetailProjectEdit').value),
                bond_date: document.getElementById('bondDetailDateEdit').value,
                amount: parseFloat(document.getElementById('bondDetailAmountEdit').value) || 0,
                bond_provider: document.getElementById('bondDetailProviderEdit').value.trim() || null,
                status: document.getElementById('bondDetailStatusEdit').value,
                remarks: document.getElementById('bondDetailRemarksEdit').value.trim() || null
            };

            // Run this step if the is finite is missing or false or the amount is at most 0.
            if (!Number.isFinite(payload.amount) || payload.amount <= 0) {
                showError('Amount is required and must contain a valid value greater than 0.');
                return;
            }
            // Run this step if the project id is missing or false or the bond date is missing or false.
            if (!payload.project_id || !payload.bond_date) {
                showError('Please fill in all required fields.');
                return;
            }

            // Use the result when this request finishes.
            apiFetch('/construction-bonds/' + bondId, { method: 'PUT', body: JSON.stringify(payload) })
                .then(function() {
                    closeBondModal();
                    showSuccess('Bond updated successfully!');
                    loadBonds();
                })
                .catch(function(error) { showError(error.message); });
        }

        // Delete bond.
        function deleteBond() {
            // Return early if the current bond row is missing or false.
            if (!currentBondRow) return;
            var bondId = currentBondRow.getAttribute('data-bond-id');
            
            // Run the open bond delete modal action.
            openBondDeleteModal('Are you sure you want to move this bond to Trash Bin?', function() {
                // Use the result when this request finishes.
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

        // Handle the click event.
        document.getElementById('bondDetailModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) closeBondModal();
        });

        // Reports
        function financeLegacyCategoryCode(code) {
            return {
                CONSTRUCTION_SUPPLY: 'CONST_SUPPLY',
                PERMITS_TAXES: 'PERMIT_TAXES_LICENSES',
                TRANSPORTATION_EXPENSES: 'TRANSPO',
                EQUIPMENT_RENTAL: 'TRANSPO',
                UTILITIES_WATER_POWER: 'UTILITIES',
                DELIVERY_EXPENSE: 'DELIVERY',
                OTHERS_SOS_ETC: 'OTHERS',
                ADMIN_SALARIES_WAGES: 'SALARIES_WAGES',
                ADMIN_PERMIT_TAXES_LICENSES: 'PERMIT_TAXES_LICENSES',
                ADMIN_TRANSPORTATION_EXPENSES: 'TRANSPO',
                ADMIN_UTILITIES_WATER_POWER: 'UTILITIES',
                ADMIN_DELIVERY_EXPENSE: 'DELIVERY',
                RENT_EXPENSE: 'RENT',
                STATIONARY_EXPENSE: 'STATIONERY',
                DEPRECIATION_EXPENSE: 'DEPRECIATION',
                REPAIR_MAINTENANCE: 'REPAIR_MAINT',
                MISCELLANEOUS_EXPENSE: 'MISC',
                PENALTY_EXPENSE: 'PENALTY',
                SSS_PHILHEALTH: 'SSS_PHILHEALTH',
                OTHERS: 'OTHERS'
            }[code] || code;
        }

        // EXPOVRALL - Load directly from fin_expense_tbl
        function loadExpovrall() {
            var month = document.getElementById('expovrallMonth').value;
            // Run this step if the month is missing or false.
            if (!month) {
                var now = new Date();
                month = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
                document.getElementById('expovrallMonth').value = month;
            }

            console.log('Loading EXPOVRALL for period:', month);

            // All categories in order
            var categoryCodes = ['CONST_SUPPLY', 'SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'RENT', 'STATIONERY', 'DEPRECIATION', 'REPAIR_MAINT', 'SSS_PHILHEALTH', 'OTHERS'];

            // Use the result when this request finishes.
            fetch(API_BASE + '/finance-expenses', {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            })
            .then(function(response) {
                // Run this step if the response ok is missing or false.
                if (!response.ok) throw new Error('Failed to fetch expenses');
                return response.json();
            })
            .then(function(expenses) {
                // Keep the matching c items.
                var categoryIds = financeCategories
                    .filter(function(c) {
                        return categoryCodes.indexOf(financeLegacyCategoryCode(c.category_code)) !== -1;
                    })
                    .map(function(c) { return c.fin_category_id || c.expense_category_id; });

                // Keep the matching e items.
                var filteredExpenses = expenses.filter(function(e) {
                    var catId = parseInt(e.fin_category_id || e.expense_category_id || 0);
                    var isMatch = categoryIds.indexOf(catId) !== -1;
                    var matchesMonth = e.expense_date && e.expense_date.startsWith(month);
                    return isMatch && matchesMonth;
                });

                var projects = {};
                // Process each e.
                filteredExpenses.forEach(function(e) {
                    var projectName = e.project_name || 'OFFICE';
                    // Run this step if the project name is missing or false.
                    if (!projects[projectName]) {
                        projects[projectName] = {};
                        // Process each cat.
                        categoryCodes.forEach(function(cat) {
                            projects[projectName][cat] = 0;
                        });
                        projects[projectName]['_total'] = 0;
                        projects[projectName]['_project_id'] = e.project_id || null;
                    }
                    var catId = parseInt(e.fin_category_id || e.expense_category_id || 0);
                    // Run the find action.
                    var category = financeCategories.find(function(c) {
                        return (c.fin_category_id || c.expense_category_id) == catId;
                    });
                    // Run this step if the category is set.
                    if (category) {
                        var code = financeLegacyCategoryCode(category.category_code);
                        // Run this step if the index of does not equal the value.
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
                // Run this step if the project names length equals 0.
                if (projectNames.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="14" style="text-align:center;padding:20px;">No data found for ' + month + '.</td></tr>';
                    return;
                }

                var grandTotal = 0;
                var categoryTotals = {};
                // Process each cat.
                categoryCodes.forEach(function(cat) { categoryTotals[cat] = 0; });

                // Process each project name.
                projectNames.forEach(function(projectName) {
                    var p = projects[projectName];
                    var tr = document.createElement('tr');
                    var cells = '<td><strong>' + projectName + '</strong></td>';
                    // Process each cat.
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
                // Process each cat.
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
        function fetchExpensesGroupedByProject(month, directCats, adminCats) {
            return new Promise(function(resolve, reject) {
                // Use the result when this request finishes.
                fetch(API_BASE + '/finance-expenses', {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                })
                .then(function(response) {
                    // Run this step if the response ok is missing or false.
                    if (!response.ok) throw new Error('Failed to fetch expenses');
                    return response.json();
                })
                .then(function(expenses) {
                    // Keep the matching c items.
                    var directCategoryIds = financeCategories
                        .filter(function(c) { return c.classification === 'direct' && directCats.indexOf(financeLegacyCategoryCode(c.category_code)) !== -1; })
                        .map(function(c) { return c.fin_category_id || c.expense_category_id; });

                    // Keep the matching c items.
                    var adminCategoryIds = financeCategories
                        .filter(function(c) { return c.classification === 'admin' && adminCats.indexOf(financeLegacyCategoryCode(c.category_code)) !== -1; })
                        .map(function(c) { return c.fin_category_id || c.expense_category_id; });

                    // Keep the matching e items.
                    var monthExpenses = expenses.filter(function(e) {
                        return e.expense_date && e.expense_date.startsWith(month);
                    });

                    // Keep the matching e items.
                    var directExpenses = monthExpenses.filter(function(e) {
                        var catId = parseInt(e.fin_category_id || e.expense_category_id || 0);
                        return directCategoryIds.indexOf(catId) !== -1;
                    });

                    // Keep the matching e items.
                    var adminExpenses = monthExpenses.filter(function(e) {
                        var catId = parseInt(e.fin_category_id || e.expense_category_id || 0);
                        return adminCategoryIds.indexOf(catId) !== -1;
                    });

                    var directProjects = {};
                    // Process each e.
                    directExpenses.forEach(function(e) {
                        var projectName = e.project_name || 'OFFICE';
                        // Run this step if the project name is missing or false.
                        if (!directProjects[projectName]) {
                            directProjects[projectName] = {};
                            // Process each cat.
                            directCats.forEach(function(cat) {
                                directProjects[projectName][cat] = 0;
                            });
                            directProjects[projectName]['_total'] = 0;
                            directProjects[projectName]['_project_id'] = e.project_id || null;
                        }
                        var catId = parseInt(e.fin_category_id || e.expense_category_id || 0);
                        // Run the find action.
                        var category = financeCategories.find(function(c) {
                            return (c.fin_category_id || c.expense_category_id) == catId;
                        });
                        // Run this step if the category is set.
                        if (category) {
                            var code = financeLegacyCategoryCode(category.category_code);
                            // Run this step if the index of does not equal the value.
                            if (directCats.indexOf(code) !== -1) {
                                var amount = parseFloat(e.amount) || 0;
                                directProjects[projectName][code] = (directProjects[projectName][code] || 0) + amount;
                                directProjects[projectName]['_total'] += amount;
                            }
                        }
                    });

                    var adminProjects = {};
                    // Process each e.
                    adminExpenses.forEach(function(e) {
                        var projectName = e.project_name || 'OFFICE';
                        // Run this step if the project name is missing or false.
                        if (!adminProjects[projectName]) {
                            adminProjects[projectName] = {};
                            // Process each cat.
                            adminCats.forEach(function(cat) {
                                adminProjects[projectName][cat] = 0;
                            });
                            adminProjects[projectName]['_total'] = 0;
                            adminProjects[projectName]['_project_id'] = e.project_id || null;
                        }
                        var catId = parseInt(e.fin_category_id || e.expense_category_id || 0);
                        // Run the find action.
                        var category = financeCategories.find(function(c) {
                            return (c.fin_category_id || c.expense_category_id) == catId;
                        });
                        // Run this step if the category is set.
                        if (category) {
                            var code = financeLegacyCategoryCode(category.category_code);
                            // Run this step if the index of does not equal the value.
                            if (adminCats.indexOf(code) !== -1) {
                                var amount = parseFloat(e.amount) || 0;
                                adminProjects[projectName][code] = (adminProjects[projectName][code] || 0) + amount;
                                adminProjects[projectName]['_total'] += amount;
                            }
                        }
                    });

                    var allProjectNames = new Set(Object.keys(directProjects).concat(Object.keys(adminProjects)));
                    var overallProjects = {};
                    // Process each project name.
                    allProjectNames.forEach(function(projectName) {
                        overallProjects[projectName] = {};
                        var dProj = directProjects[projectName] || {};
                        var aProj = adminProjects[projectName] || {};
                        
                        // Process each cat.
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
                        adminTotals: adminExpenses
                    });
                })
                .catch(function(error) {
                    reject(error);
                });
            });
        }

        // EXP DIRECT
        function loadExpDirect() {
            var month = document.getElementById('expdirectMonth').value;
            // Run this step if the month is missing or false.
            if (!month) {
                var now = new Date();
                month = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
                document.getElementById('expdirectMonth').value = month;
            }

            var directCategoryCodes = ['CONST_SUPPLY', 'SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'OTHERS'];
            var adminCategoryCodes = ['RENT', 'STATIONERY', 'DEPRECIATION', 'REPAIR_MAINT', 'MISC', 'PENALTY', 'SSS_PHILHEALTH'];

            // Use the result when this request finishes.
            fetchExpensesGroupedByProject(month, directCategoryCodes, adminCategoryCodes)
                .then(function(groupedData) {
                    var directProjects = groupedData.directProjects;
                    var adminProjects = groupedData.adminProjects;

                    var tbody = document.getElementById('expdirectBody');
                    tbody.innerHTML = '';

                    var projectNames = Object.keys(directProjects).sort();
                    // Run this step if the project names length equals 0.
                    if (projectNames.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:20px;">No data found for ' + month + '.</td></tr>';
                        return;
                    }

                    var grandTotal = 0;
                    var totalAdmin = 0;
                    var categoryTotals = {};
                    // Process each cat.
                    directCategoryCodes.forEach(function(cat) { categoryTotals[cat] = 0; });

                    // Process each project name.
                    projectNames.forEach(function(projectName) {
                        var p = directProjects[projectName];
                        var a = adminProjects[projectName];

                        var tr = document.createElement('tr');
                        var cells = '<td><strong>' + projectName + '</strong></td>';
                        // Process each cat.
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

                    // Keep the matching p name items.
                    var onlyAdminProjects = Object.keys(adminProjects).filter(function(pName) {
                        return !directProjects[pName];
                    });
                    // Process each project name.
                    onlyAdminProjects.forEach(function(projectName) {
                        var a = adminProjects[projectName];
                        var tr = document.createElement('tr');
                        var cells = '<td><strong>' + projectName + '</strong></td>';
                        // Process each cat.
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
                    // Process each cat.
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

        // ADMIN EXP
        function loadAdminExp() {
            var month = document.getElementById('adminexpMonth').value;
            // Run this step if the month is missing or false.
            if (!month) {
                var now = new Date();
                month = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
                document.getElementById('adminexpMonth').value = month;
            }

            var directCategoryCodes = ['CONST_SUPPLY', 'SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'OTHERS'];
            var adminCategoryCodes = ['SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'RENT', 'STATIONERY', 'DEPRECIATION', 'REPAIR_MAINT', 'MISC', 'PENALTY', 'SSS_PHILHEALTH', 'OTHERS'];

            // Use the result when this request finishes.
            fetchExpensesGroupedByProject(month, directCategoryCodes, adminCategoryCodes)
                .then(function(groupedData) {
                    var adminProjects = groupedData.adminProjects;

                    var tbody = document.getElementById('adminexpBody');
                    tbody.innerHTML = '';

                    var projectNames = Object.keys(adminProjects).sort();
                    // Run this step if the project names length equals 0.
                    if (projectNames.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="15" style="text-align:center;padding:20px;">No admin expenses found for ' + month + '.</td></tr>';
                        return;
                    }

                    var grandTotal = 0;
                    var categoryTotals = {};
                    // Process each cat.
                    adminCategoryCodes.forEach(function(cat) { categoryTotals[cat] = 0; });

                    // Process each project name.
                    projectNames.forEach(function(projectName) {
                        var p = adminProjects[projectName];
                        var tr = document.createElement('tr');
                        var cells = '<td><strong>' + projectName + '</strong></td>';
                        // Process each cat.
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
                    // Process each cat.
                    adminCategoryCodes.forEach(function(cat) {
                        cells += '<td><strong>' + formatCurrency(categoryTotals[cat]) + '</strong></td>';
                    });
                    cells += '<td><strong>' + formatCurrency(grandTotal) + '</strong></td>';
                    tr.innerHTML = cells;
                    tbody.appendChild(tr);
                })
                .catch(function(error) {
                    console.error('Error loading ADMIN EXP:', error);
                    showError('Error loading ADMIN EXP: ' + error.message);
                    document.getElementById('adminexpBody').innerHTML = '<tr><td colspan="15" style="text-align:center;padding:20px;color:#d32f2f;">Error loading data: ' + error.message + '</td></tr>';
                });
        }

        // DIRECT EXP
        function loadDirectExp() {
            var month = document.getElementById('directexpMonth').value;
            // Run this step if the month is missing or false.
            if (!month) {
                var now = new Date();
                month = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
                document.getElementById('directexpMonth').value = month;
            }

            var directCategoryCodes = ['CONST_SUPPLY', 'SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'OTHERS'];
            var adminCategoryCodes = ['RENT', 'STATIONERY', 'DEPRECIATION', 'REPAIR_MAINT', 'MISC', 'PENALTY', 'SSS_PHILHEALTH'];

            // Use the result when this request finishes.
            fetchExpensesGroupedByProject(month, directCategoryCodes, adminCategoryCodes)
                .then(function(groupedData) {
                    var directProjects = groupedData.directProjects;
                    var adminProjects = groupedData.adminProjects;

                    var tbody = document.getElementById('directexpBody');
                    tbody.innerHTML = '';

                    var projectNames = Object.keys(directProjects).sort();
                    // Run this step if the project names length equals 0.
                    if (projectNames.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:20px;">No data found for ' + month + '.</td></tr>';
                        return;
                    }

                    var grandTotal = 0;
                    var totalAdmin = 0;
                    var categoryTotals = {};
                    // Process each cat.
                    directCategoryCodes.forEach(function(cat) { categoryTotals[cat] = 0; });

                    // Process each project name.
                    projectNames.forEach(function(projectName) {
                        var p = directProjects[projectName];
                        var a = adminProjects[projectName];

                        var tr = document.createElement('tr');
                        var cells = '<td><strong>' + projectName + '</strong></td>';
                        // Process each cat.
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

                    // Keep the matching p name items.
                    var onlyAdminProjects = Object.keys(adminProjects).filter(function(pName) {
                        return !directProjects[pName];
                    });
                    // Process each project name.
                    onlyAdminProjects.forEach(function(projectName) {
                        var a = adminProjects[projectName];
                        var tr = document.createElement('tr');
                        var cells = '<td><strong>' + projectName + '</strong></td>';
                        // Process each cat.
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
                    // Process each cat.
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

        // OVERALL EXP
        function loadOverallExp() {
            var month = document.getElementById('overallexpMonth').value;
            // Run this step if the month is missing or false.
            if (!month) {
                var now = new Date();
                month = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
                document.getElementById('overallexpMonth').value = month;
            }

            var directCategoryCodes = ['CONST_SUPPLY', 'SALARIES_WAGES', 'PERMIT_TAXES_LICENSES', 'TRANSPO', 'UTILITIES', 'DELIVERY', 'OTHERS'];
            var adminCategoryCodes = ['RENT', 'STATIONERY', 'DEPRECIATION', 'REPAIR_MAINT', 'MISC', 'PENALTY', 'SSS_PHILHEALTH'];

            // Use the result when this request finishes.
            fetchExpensesGroupedByProject(month, directCategoryCodes, adminCategoryCodes)
                .then(function(groupedData) {
                    var overallProjects = groupedData.overallProjects;

                    var tbody = document.getElementById('overallexpBody');
                    tbody.innerHTML = '';

                    var projectNames = Object.keys(overallProjects).sort();
                    // Run this step if the project names length equals 0.
                    if (projectNames.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:20px;">No data found for ' + month + '.</td></tr>';
                        return;
                    }

                    var grandTotal = 0;
                    var totalAdmin = 0;
                    var categoryTotals = {};
                    // Process each cat.
                    directCategoryCodes.forEach(function(cat) { categoryTotals[cat] = 0; });

                    // Process each project name.
                    projectNames.forEach(function(projectName) {
                        var p = overallProjects[projectName];
                        var tr = document.createElement('tr');
                        var cells = '<td><strong>' + projectName + '</strong></td>';
                        // Process each cat.
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
                    // Process each cat.
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
        var profitRequest = 0;
        // Update contract totals.
        function updateContractTotals() {
            var table = document.getElementById('profitTable');
            var totalRow = document.querySelector('#profitTotals tr');
            var sums = Array(10).fill(0);
            // Process each row.
            document.querySelectorAll('#profitBody tr[data-price-totals]').forEach(function(row) {
                // Return early if the hidden is set or the style display equals none.
                if (row.hidden || row.style.display === 'none') return;
                // Process each value.
                JSON.parse(row.dataset.priceTotals).forEach(function(value, index) { sums[index] += Math.round(value * 100); });
            });
            var columns = table.tHead.rows[0].cells.length;
            // Repeat while the cells length is below the columns.
            while (totalRow.cells.length < columns) totalRow.insertCell();
            // Process each value.
            sums.forEach(function(value, index) { totalRow.cells[index + 3].textContent = formatCurrency(value / 100); });
        }
        document.getElementById('profitTable').addEventListener('pfims:page-rendered', updateContractTotals);

        // Load profit.
        function loadProfit() {
            var request = ++profitRequest;
            var year = document.getElementById('profitYear').value;
            var params = new URLSearchParams({ per_page: '100' });
            // Run this step if the year is set.
            if (year) {
                params.set('start_date', year + '-01-01');
                params.set('end_date', year + '-12-31');
            }
            // Load page.
            function loadPage(page, rows) {
                params.set('page', String(page));
                // Use the result when this request finishes.
                return apiFetch('/reports/data/contracts?' + params.toString()).then(function(result) {
                    rows.push.apply(rows, result.rows || []);
                    return page < Number(result.pagination && result.pagination.last_page || 1)
                        ? loadPage(page + 1, rows) : rows;
                });
            }

            // Use the result when this request finishes.
            loadPage(1, [])
                .then(function(data) {
                    // Return early if the request does not equal the profit request.
                    if (request !== profitRequest) return;
                    // Run the sort action.
                    data.sort(function(a, b) { return Number(b.contract_id) - Number(a.contract_id); });
                    var tbody = document.getElementById('profitBody');
                    tbody.innerHTML = '';

                    // Run this step if the data is missing or false or the data length is missing or false.
                    if (!data || !data.length) {
                        tbody.innerHTML = '<tr><td colspan="13" style="text-align:center;padding:20px;">No data found. Please add budgets to projects.</td></tr>';
                        return;
                    }

                    var hasData = false;
                    // Process each row.
                    data.forEach(function(row) {
                        var contractPrice = parseFloat(row.original_contract_price) || 0;
                        // Return early if the contract price equals 0 and the parse int equals 0.
                        if (contractPrice === 0 && parseInt(row.contract_id) === 0) {
                            return;
                        }
                        hasData = true;
                        var tr = document.createElement('tr');
                        var profitPayment = parseFloat(row.profit_loss_payment_basis) || 0;
                        var profitContract = parseFloat(row.profit_loss_contract_basis) || 0;
                        tr.setAttribute('data-project-id', row.project_id);
                        tr.setAttribute('data-contract-id', row.contract_id || '');
                        // Build a value for each value.
                        tr.dataset.priceTotals = JSON.stringify([contractPrice, row.additional_works_contract, row.total_contract_price, row.original_payment_received, row.additional_works_payment, row.total_payment, row.project_expense, row.accounts_receivable, profitPayment, profitContract].map(function(value) { return Number(value) || 0; }));
                        tr.setAttribute('data-contract-price', contractPrice);
                        tr.setAttribute('data-addl-works', row.additional_works_contract || 0);
                        tr.setAttribute('data-payment', row.original_payment_received || 0);
                        tr.setAttribute('data-addl-payment', row.additional_works_payment || 0);
                        tr.setAttribute('data-remarks', row.remarks || '');
                        tr.style.cursor = 'pointer';
                        tr.onclick = function() { openContractViewModal(this); };
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

                    // Run this step if the has data is missing or false.
                    if (!hasData) {
                        tbody.innerHTML = '<tr><td colspan="13" style="text-align:center;padding:20px;">No projects with budgets found. Please add budgets to projects.</td></tr>';
                    }
                    filterFinanceRows('profitSearch', 'profitBody');
                    updateContractTotals();
                })
                .catch(function(error) {
                    // Return early if the request does not equal the profit request.
                    if (request !== profitRequest) return;
                    showError('Error loading Profit/Loss: ' + error.message);
                });
        }

        // RECEIVABLES/PAYABLES
        function loadReceivables() {
            var type = document.getElementById('receivableType').value;

            var params = new URLSearchParams({ entry_type: type });
            var month = document.getElementById('receivableMonth').value;
            // Run this step if the month is set.
            if (month) params.set('month', month);
            // Use the result when this request finishes.
            apiFetch('/reports/receivable-payable?' + params.toString())
                .then(function(data) {
                    var tbody = document.getElementById('receivableBody');
                    tbody.innerHTML = '';

                    // Run this step if the data is missing or false or the data length is missing or false.
                    if (!data || !data.length) {
                        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:20px;">No data found.</td></tr>';
                        return;
                    }

                    var total = 0;
                    // Process each row.
                    data.forEach(function(row) {
                        var tr = document.createElement('tr');
                        var amt30d = parseFloat(row.amount_30d) || 0;
                        var amt60d = parseFloat(row.amount_31_60d) || 0;
                        var amt90d = parseFloat(row.amount_61_90d) || 0;
                        var amt120d = parseFloat(row.amount_91_120d) || 0;
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
                    filterFinanceRows('receivableSearch', 'receivableBody');
                })
                .catch(function(error) {
                    showError('Error loading Receivables/Payables: ' + error.message);
                });
        }

        // CASH ASSET
        function loadCashAsset() {
            var month = document.getElementById('cashMonth').value;
            var query = month ? '?period=' + month + '-01' : '';
            // Use the result when this request finishes.
            apiFetch('/reports/cash-asset' + query)
                .then(function(data) {
                    var tbody = document.getElementById('cashBody');
                    tbody.innerHTML = '';

                    // Run this step if the data is missing or false or the data length is missing or false.
                    if (!data || !data.length) {
                        tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;padding:20px;">No data found for this period.</td></tr>';
                        return;
                    }

                    var total = 0;
                    // Process each row.
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
                            '<td>' + escapeFinanceHtml(String(row.period_month || '').substring(0, 7)) + '</td>' +
                            '<td>' + formatCurrency(row.balance_amount || 0) + '</td>';
                        tbody.appendChild(tr);
                        total += parseFloat(row.balance_amount) || 0;
                    });

                    var tr = document.createElement('tr');
                    tr.className = 'total-row';
                    tr.innerHTML = '<td colspan="2"><strong>TOTAL CASH ASSET</strong></td><td><strong>' + formatCurrency(total) + '</strong></td>';
                    tbody.appendChild(tr);
                    filterFinanceRows('cashSearch', 'cashBody');
                })
                .catch(function(error) {
                    showError('Error loading Cash Asset: ' + error.message);
                });
        }

        // Cash asset detail modal
        var currentCashRow = null;
        var isCashEditMode = false;

        // Open cash modal.
        function openCashModal(row) {
            currentCashRow = row;
            document.getElementById('cashModalTitle').textContent = 'Cash Asset Details';
            
            var accountName = row.dataset.accountName || '';
            var period = row.dataset.period || '';
            var balance = parseFloat(row.dataset.balance) || 0;
            
            document.getElementById('cashDetailAccount').textContent = accountName;
            document.getElementById('cashDetailPeriod').textContent = period ? new Date(period).toLocaleDateString('en-US', { year: 'numeric', month: '2-digit' }) : '—';
            document.getElementById('cashDetailBalance').textContent = formatCurrency(balance);
            
            document.getElementById('cashDetailAccountEdit').value = row.dataset.accountId || '';
            document.getElementById('cashDetailPeriodEdit').value = period ? period.substring(0, 7) : '';
            document.getElementById('cashDetailBalanceEdit').value = balance;
            
            document.getElementById('cashDetailAccountDisplay').textContent = accountName;

            // Run this step if the is cash edit mode is set.
            if (isCashEditMode) toggleCashEdit();
            isCashEditMode = false;
            document.getElementById('cashDetailModal').classList.remove('is-editing');
            document.getElementById('cashDetailEditBtn').style.display = 'inline-block';
            document.getElementById('cashDetailDeleteBtn').style.display = 'none';
            document.getElementById('cashDetailSaveBtn').style.display = 'none';
            
            // Process each el.
            document.querySelectorAll('#cashDetailModal .detail-edit').forEach(function(el) { 
                el.style.display = 'none'; 
            });
            // Process each el.
            document.querySelectorAll('#cashDetailModal .detail-value').forEach(function(el) { 
                el.style.display = ''; 
            });
            document.getElementById('cashDetailAccountDisplay').style.display = 'none';

            document.getElementById('cashDetailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Close cash modal.
        function closeCashModal() {
            document.getElementById('cashDetailModal').classList.remove('active');
            document.body.style.overflow = '';
            // Run this step if the is cash edit mode is set.
            if (isCashEditMode) toggleCashEdit();
        }

        // Switch cash edit.
        function toggleCashEdit() {
            isCashEditMode = !isCashEditMode;
            var editBtn = document.getElementById('cashDetailEditBtn');
            var deleteBtn = document.getElementById('cashDetailDeleteBtn');
            var saveBtn = document.getElementById('cashDetailSaveBtn');
            
            var editableFields = ['cashDetailPeriodEdit', 'cashDetailBalanceEdit'];
            var displayFields = ['cashDetailAccount', 'cashDetailPeriod', 'cashDetailBalance'];

            // Run this step if the is cash edit mode is set.
            if (isCashEditMode) {
                document.getElementById('cashDetailModal').classList.add('is-editing');
                document.getElementById('cashModalTitle').textContent = 'Edit Cash Asset';
                editBtn.style.display = 'none';
                deleteBtn.style.display = 'inline-block';
                saveBtn.style.display = 'inline-block';
                
                // Process each id.
                displayFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    // Run this step if the el is set.
                    if (el) el.style.display = 'none';
                });
                
                // Process each id.
                editableFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    // Run this step if the el is set.
                    if (el) el.style.display = '';
                });
                
                document.getElementById('cashDetailAccountDisplay').style.display = '';
                document.getElementById('cashDetailAccountEdit').style.display = 'none';
                
            } else {
                document.getElementById('cashDetailModal').classList.remove('is-editing');
                document.getElementById('cashModalTitle').textContent = 'Cash Asset Details';
                editBtn.style.display = 'inline-block';
                deleteBtn.style.display = 'none';
                saveBtn.style.display = 'none';
                
                // Process each id.
                displayFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    // Run this step if the el is set.
                    if (el) el.style.display = '';
                });
                
                // Process each id.
                editableFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    // Run this step if the el is set.
                    if (el) el.style.display = 'none';
                });
                
                document.getElementById('cashDetailAccountDisplay').style.display = 'none';
            }
        }

        // Save cash detail.
        function saveCashDetail() {
            // Return early if the current cash row is missing or false.
            if (!currentCashRow) return;
            var cashId = currentCashRow.getAttribute('data-cash-id');
            var period = document.getElementById('cashDetailPeriodEdit').value;
            var balance = parseFloat(document.getElementById('cashDetailBalanceEdit').value) || 0;

            // Run this step if the period is missing or false.
            if (!period) {
                showError('Please select a month.');
                return;
            }
            // Run this step if the balance is below 0.
            if (balance < 0) {
                showError('Balance must be greater than or equal to 0.');
                return;
            }

            var payload = {
                period_month: period + '-01',
                balance_amount: balance
            };

            // Use the result when this request finishes.
            apiFetch('/cash-positions/' + cashId, { method: 'PUT', body: JSON.stringify(payload) })
                .then(function() {
                    closeCashModal();
                    showSuccess('Cash Asset updated successfully!');
                    loadCashAsset();
                })
                .catch(function(error) { showError(error.message); });
        }

        // Delete cash position.
        function deleteCashPosition() {
            // Return early if the current cash row is missing or false.
            if (!currentCashRow) return;
            var cashId = currentCashRow.getAttribute('data-cash-id');
            
            // Run the open delete modal action.
            openDeleteModal('Are you sure you want to move this Cash Asset record to Trash Bin?', function() {
                // Use the result when this request finishes.
                apiFetch('/cash-positions/' + cashId, { method: 'DELETE' })
                    .then(function() {
                        closeCashModal();
                        showSuccess('Cash Asset deleted successfully!');
                        loadCashAsset();
                        currentCashRow = null;
                    })
                    .catch(function(error) { showError(error.message); });
            });
        }

        // Handle the click event.
        document.getElementById('cashDetailModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) closeCashModal();
        });

        // REPAIR
        function loadRepair() {
            var month = document.getElementById('repairMonth').value;
            // Return early if the month is missing or false.
            if (!month) return;
            var period = month + '-01';

            var url = '/reports/repair-total?period=' + period;

            // Use the result when this request finishes.
            apiFetch(url)
                .then(function(data) {
                    var tbody = document.getElementById('repairBody');
                    tbody.innerHTML = '';

                    // Run this step if the data is missing or false or the data length is missing or false.
                    if (!data || !data.length) {
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;">No data found.</td></tr>';
                        return;
                    }

                    // Process each row.
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
            var month = document.getElementById('backhoeMonth').value;
            
            var url = '/reports/backhoe-profitability';
            var params = [];
            // Run this step if the month is set.
            if (month) {
                params.push('period=' + month + '-01');
            }
            // Run this step if the params length is above 0.
            if (params.length > 0) {
                url += '?' + params.join('&');
            }

            // Handle a failed request.
            Promise.all([
                apiFetch(url),
                apiFetch('/equipment-expenses').catch(function() { return []; }),
                apiFetch('/equipment-rental-income').catch(function() { return []; })
            ])
                .then(function(results) {
                    var data = results[0];
                    equipmentExpenseRecords = results[1] || [];
                    equipmentRentalRecords = results[2] || [];
                    equipmentRecordsByPeriod = {};
                    // Process each record.
                    equipmentExpenseRecords.forEach(function(record) {
                        var key = record.asset_id + '|' + equipmentDate(record.expense_date || '').substring(0, 7);
                        // Run this step if the key is missing or false.
                        if (!equipmentRecordsByPeriod[key]) equipmentRecordsByPeriod[key] = { expenses: [], rentals: [] };
                        equipmentRecordsByPeriod[key].expenses.push(record);
                    });
                    // Process each record.
                    equipmentRentalRecords.forEach(function(record) {
                        var key = record.asset_id + '|' + equipmentDate(record.period_month || '').substring(0, 7);
                        // Run this step if the key is missing or false.
                        if (!equipmentRecordsByPeriod[key]) equipmentRecordsByPeriod[key] = { expenses: [], rentals: [] };
                        equipmentRecordsByPeriod[key].rentals.push(record);
                    });
                    var tbody = document.getElementById('backhoeBody');
                    tbody.innerHTML = '';

                    var assetPeriodData = {};
                    var reportRows = Array.isArray(data) ? data.slice() : [];

                    // The profitability report is expense-anchored, so a valid
                    // rental-income-only period has no report row. Add a
                    // lightweight synthetic row for those existing records so
                    // the period still exposes View/Edit actions and remains
                    // editable through the rental-income API.
                    var reportPeriodKeys = {};
                    // Process each row.
                    reportRows.forEach(function(row) {
                        reportPeriodKeys[row.asset_id + '|' + equipmentDate(row.period_month || '').substring(0, 7)] = true;
                    });
                    // Process each record.
                    equipmentRentalRecords.forEach(function(record) {
                        var rentalPeriod = equipmentDate(record.period_month || '').substring(0, 7);
                        // Run the find action.
                        var rentalAsset = assets.find(function(item) { return String(item.asset_id) === String(record.asset_id); });
                        var rentalKey = record.asset_id + '|' + rentalPeriod;
                        // Return early if the value checks match.
                        if (!rentalPeriod || reportPeriodKeys[rentalKey] || (month && rentalPeriod !== month)) return;
                        // Return early if the rental asset is missing or false or the asset type does not equal heavy_equipment.
                        if (!rentalAsset || rentalAsset.asset_type !== 'heavy_equipment') return;
                        reportRows.push({
                            asset_id: record.asset_id,
                            asset_name: rentalAsset.asset_name,
                            expense_type: null,
                            amount: 0,
                            period_month: rentalPeriod + '-01',
                            rental_income: parseFloat(record.amount) || 0,
                            net_income: parseFloat(record.amount) || 0
                        });
                        reportPeriodKeys[rentalKey] = true;
                    });

                    // Run this step if the report rows length is missing or false.
                    if (!reportRows.length) {
                        tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:20px;">No data found.</td></tr>';
                        return;
                    }

                    // Process each row.
                    reportRows.forEach(function(row) {
                        var key = row.asset_id + '|' + (row.period_month || '');
                        // Run this step if the key is missing or false.
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
                        var expenseType = row.expense_type || '';
                        var amount = parseFloat(row.amount) || 0;
                        // Run this step if the expense type equals gas_diesel.
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
                        assetPeriodData[key].total_expense += amount;
                        // Run this step if the rental income is set.
                        if (row.rental_income) {
                            assetPeriodData[key].rental_income = parseFloat(row.rental_income) || 0;
                        }
                        // Run this step if the net income is set.
                        if (row.net_income) {
                            assetPeriodData[key].net_income = parseFloat(row.net_income) || 0;
                        }
                    });

                    var grandExpense = 0;
                    var grandIncome = 0;
                    var grandNet = 0;

                    // Process each key.
                    Object.keys(assetPeriodData).forEach(function(key) {
                        var a = assetPeriodData[key];
                        var tr = document.createElement('tr');
                        var net = a.net_income;
                        
                        var periodKey = a.asset_id + '|' + String(a.period_month || '').substring(0, 7);
                        var siteRecords = equipmentRecordsByPeriod[periodKey] || {expenses: [], rentals: []};
                        // Build a value for each record.
                        var projectSites = Array.from(new Set(siteRecords.expenses.concat(siteRecords.rentals).map(function(record) {
                            return record.project && record.project.project_name ? record.project.project_name : 'Office/Admin';
                        }))).join(', ');
                        tr.innerHTML = '<td><strong>' + a.asset_name + '</strong></td>' +
                            '<td>' + String(a.period_month || '').substring(0, 7) + '</td>' +
                            '<td>' + escapeFinanceHtml(projectSites || 'Office/Admin') + '</td>' +
                            '<td>' + formatCurrency(a.gas_diesel) + '</td>' +
                            '<td>' + formatCurrency(a.payroll_operator) + '</td>' +
                            '<td>' + formatCurrency(a.repair) + '</td>' +
                            '<td>' + formatCurrency(a.other) + '</td>' +
                            '<td>' + formatCurrency(a.delivery) + '</td>' +
                            '<td>' + formatCurrency(a.transportation) + '</td>' +
                            '<td><strong>' + formatCurrency(a.total_expense) + '</strong></td>' +
                            '<td>' + formatCurrency(a.rental_income) + '</td>' +
                            '<td class="' + (net < 0 ? 'amount-negative' : 'amount-positive') + '">' + formatCurrency(net) + '</td>' +
                            '<td class="action-cell"><button type="button" class="pfims-row-action" onclick="openEquipmentDetailModal(\'' + periodKey.replace(/'/g, "\\'") + '\')" title="View equipment details" aria-label="View equipment details"><img src="/images/view.jpg" alt=""></button></td>';
                        tbody.appendChild(tr);
                        grandExpense += a.total_expense;
                        grandIncome += a.rental_income;
                        grandNet += net;
                    });

                    var tr = document.createElement('tr');
                    tr.className = 'total-row';
                    tr.innerHTML = '<td><strong>GRAND TOTAL</strong></td>' + '<td></td>'.repeat(8) +
                        '<td><strong>' + formatCurrency(grandExpense) + '</strong></td>' +
                        '<td><strong>' + formatCurrency(grandIncome) + '</strong></td>' +
                        '<td class="' + (grandNet < 0 ? 'amount-negative' : 'amount-positive') + '"><strong>' + formatCurrency(grandNet) + '</strong></td><td>—</td>';
                    tbody.appendChild(tr);
                })
                .catch(function(error) {
                    showError('Error loading Backhoe data: ' + error.message);
                });
        }

        // Close equipment detail modal.
        function closeEquipmentDetailModal() {
            var modal = document.getElementById('equipmentDetailModal');
            // Run this step if the modal is set.
            if (modal) modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Open equipment detail modal.
        function openEquipmentDetailModal(key) {
            currentEquipmentPeriodKey = key;
            var records = equipmentRecordsByPeriod[key] || { expenses: [], rentals: [] };
            var parts = key.split('|');
            // Run the find action.
            var asset = assets.find(function(item) { return String(item.asset_id) === String(parts[0]); });
            // Combine the items into one result.
            var expensesTotal = records.expenses.reduce(function(sum, row) { return sum + (parseFloat(row.amount) || 0); }, 0);
            // Combine the items into one result.
            var rentalTotal = records.rentals.reduce(function(sum, row) { return sum + (parseFloat(row.amount) || 0); }, 0);
            document.getElementById('equipmentDetailSummary').innerHTML =
                '<div class="detail-item"><label>Asset</label><span class="detail-value">' + escapeFinanceHtml(asset ? asset.asset_name : parts[0]) + '</span></div>' +
                '<div class="detail-item"><label>Period</label><span class="detail-value">' + escapeFinanceHtml(parts[1]) + '</span></div>' +
                '<div class="detail-item"><label>Total expenses</label><span class="detail-value">' + formatCurrency(expensesTotal) + '</span></div>' +
                '<div class="detail-item"><label>Rental income</label><span class="detail-value">' + formatCurrency(rentalTotal) + '</span></div>';
            var body = document.getElementById('equipmentDetailBody');
            var rows = [];
            // Process each row.
            records.expenses.forEach(function(row) {
                rows.push('<tr><td>Expense (' + escapeFinanceHtml(String(row.expense_type || '').replace('_', ' ')) + ')</td><td>' + escapeFinanceHtml(equipmentDate(row.expense_date)) + '</td><td>' + escapeFinanceHtml(row.project && row.project.project_name ? row.project.project_name : 'Office/Admin') + '</td><td>' + formatCurrency(row.amount) + '</td><td>' + escapeFinanceHtml(row.remarks || '—') + '</td><td><button type="button" class="btn-edit-project" onclick="editEquipmentRecord(\'expense\',' + row.equip_expense_id + ')">Edit</button> <button type="button" class="btn-delete" onclick="deleteEquipmentRecord(\'expense\',' + row.equip_expense_id + ')">Delete</button></td></tr>');
            });
            // Process each row.
            records.rentals.forEach(function(row) {
                rows.push('<tr><td>Rental income</td><td>' + escapeFinanceHtml(equipmentDate(row.period_month || '').substring(0, 7)) + '</td><td>' + escapeFinanceHtml(row.project && row.project.project_name ? row.project.project_name : 'Office/Admin') + '</td><td>' + formatCurrency(row.amount) + '</td><td>' + escapeFinanceHtml(row.remarks || '—') + '</td><td><button type="button" class="btn-edit-project" onclick="editEquipmentRecord(\'rental\',' + row.rental_income_id + ')">Edit</button> <button type="button" class="btn-delete" onclick="deleteEquipmentRecord(\'rental\',' + row.rental_income_id + ')">Delete</button></td></tr>');
            });
            body.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="6" style="text-align:center;padding:20px;">No underlying records.</td></tr>';
            document.getElementById('equipmentDetailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Get the equipment date.
        function equipmentDate(value) {
            // Return early if the value is missing or false.
            if (!value) return '—';
            var date = new Date(value);
            return isNaN(date.getTime()) ? String(value).substring(0,10) : new Intl.DateTimeFormat('en-CA', {timeZone:'Asia/Manila',year:'numeric',month:'2-digit',day:'2-digit'}).format(date);
        }
        // Open equipment period edit.
        function openEquipmentPeriodEdit() {
            var parts = currentEquipmentPeriodKey.split('|');
            // Keep the matching a items.
            document.getElementById('equipmentPeriodAsset').innerHTML = assets.filter(function(a) { return a.asset_type === 'heavy_equipment'; }).map(function(a) { return '<option value="' + a.asset_id + '">' + escapeFinanceHtml(a.asset_name) + '</option>'; }).join('');
            document.getElementById('equipmentPeriodAsset').value = parts[0];
            document.getElementById('equipmentPeriodMonth').value = parts[1];
            closeEquipmentDetailModal(); document.getElementById('equipmentPeriodEditModal').classList.add('active'); document.body.style.overflow='hidden';
        }
        // Close equipment period edit.
        function closeEquipmentPeriodEdit() { document.getElementById('equipmentPeriodEditModal').classList.remove('active'); document.body.style.overflow=''; }
        // Get the equipment period payload.
        function equipmentPeriodPayload() { var parts=currentEquipmentPeriodKey.split('|'); return {asset_id:Number(parts[0]),period:parts[1]}; }
        // Save equipment period.
        function saveEquipmentPeriod() {
            var payload=equipmentPeriodPayload(); payload.target_asset_id=Number(document.getElementById('equipmentPeriodAsset').value); payload.target_period=document.getElementById('equipmentPeriodMonth').value;
            // Run this step if the target asset id is missing or false or the target period is missing or false.
            if (!payload.target_asset_id || !payload.target_period) { showError('Please select an asset and period.'); return; }
            // Run the open delete modal action.
            openDeleteModal('Update this asset and period? All underlying expenses and rental income will move with it. Expense dates will move to the selected month, retaining their day where possible.', function() {
                // Use the result when this request finishes.
                apiFetch('/equipment-period',{method:'PUT',body:JSON.stringify(payload)}).then(function() { closeEquipmentPeriodEdit(); document.getElementById('backhoeMonth').value=payload.target_period; showSuccess('Equipment asset and period updated.'); loadBackhoe(); }).catch(function(error) { showError(error.message); });
            }, true);
        }
        // Delete equipment period.
        function deleteEquipmentPeriod() {
            var payload=equipmentPeriodPayload();
            // Run the open delete modal action.
            openDeleteModal('Are you sure you want to move this asset and period, including its expenses and rental income, to Trash Bin?',function() {
                // Use the result when this request finishes.
                apiFetch('/equipment-period',{method:'DELETE',body:JSON.stringify(payload)}).then(function() { closeEquipmentDetailModal(); showSuccess('Equipment period and underlying records deleted.'); loadBackhoe(); }).catch(function(error) { showError(error.message); });
            });
        }

        // Get the escape finance html.
        function escapeFinanceHtml(value) {
            // Run the replace action.
            return String(value == null ? '' : value).replace(/[&<>'"]/g, function(character) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character];
            });
        }

        // Get the edit equipment record.
        function editEquipmentRecord(type, id) {
            var source = type === 'expense' ? equipmentExpenseRecords : equipmentRentalRecords;
            // Run the find action.
            var row = source.find(function(item) { return String(type === 'expense' ? item.equip_expense_id : item.rental_income_id) === String(id); });
            // Run this step if the row is missing or false.
            if (!row) { showError('Equipment record not found.'); return; }
            closeEquipmentDetailModal();
            // Run this step if the type equals expense.
            if (type === 'expense') {
                openAddBackhoeExpenseModal();
                equipmentEditState = { type: type, id: id };
                document.getElementById('backhoeExpenseAsset').value = row.asset_id;
                document.getElementById('backhoeExpenseProject').value = row.project_id || '';
                document.getElementById('backhoeExpenseProject').required = true;
                document.querySelector('#backhoeExpenseProject').closest('.form-group').querySelector('label').textContent = 'Project Site *';
                document.getElementById('backhoeExpenseType').value = row.expense_type;
                document.getElementById('backhoeExpenseAmount').value = row.amount;
                document.getElementById('backhoeExpenseDate').value = equipmentDate(row.expense_date);
                document.getElementById('backhoeExpenseRemarks').value = row.remarks || '';
                document.getElementById('backhoeExpenseModalTitle').textContent = 'Edit Equipment Expense';
                document.getElementById('backhoeExpenseSaveBtn').textContent = 'Save Changes';
            } else {
                openAddBackhoeRentalModal();
                equipmentEditState = { type: type, id: id };
                document.getElementById('backhoeRentalAsset').value = row.asset_id;
                document.getElementById('backhoeRentalProject').value = row.project_id || '';
                document.getElementById('backhoeRentalProject').required = true;
                document.querySelector('#backhoeRentalProject').closest('.form-group').querySelector('label').textContent = 'Project Site *';
                document.getElementById('backhoeRentalPeriod').value = equipmentDate(row.period_month || '').substring(0, 7);
                document.getElementById('backhoeRentalAmount').value = row.amount;
                document.getElementById('backhoeRentalRemarks').value = row.remarks || '';
                document.getElementById('backhoeRentalModalTitle').textContent = 'Edit Rental Income';
                document.getElementById('backhoeRentalSaveBtn').textContent = 'Save Changes';
            }
        }

        // Delete equipment record.
        function deleteEquipmentRecord(type, id) {
            // Run the open delete modal action.
            openDeleteModal('Are you sure you want to move this equipment record to Trash Bin?', function() {
                // Use the result when this request finishes.
                apiFetch((type === 'expense' ? '/equipment-expenses/' : '/equipment-rental-income/') + id, { method: 'DELETE' })
                    .then(function() { closeEquipmentDetailModal(); showSuccess('Equipment record deleted successfully.'); loadBackhoe(); })
                    .catch(function(error) { showError(error.message); });
            });
        }

        // Open equipment edit for period.
        function openEquipmentEditForPeriod(key) {
            var records = equipmentRecordsByPeriod[key] || { expenses: [], rentals: [] };
            var row = records.expenses[0];
            // Return early if the row is set.
            if (row) return editEquipmentRecord('expense', row.equip_expense_id);
            row = records.rentals[0];
            // Return early if the row is set.
            if (row) return editEquipmentRecord('rental', row.rental_income_id);
            showError('No equipment record is available to edit.');
        }

        // Open equipment delete for period.
        function openEquipmentDeleteForPeriod(key) {
            var records = equipmentRecordsByPeriod[key] || { expenses: [], rentals: [] };
            var row = records.expenses[0];
            // Return early if the row is set.
            if (row) return deleteEquipmentRecord('expense', row.equip_expense_id);
            row = records.rentals[0];
            // Return early if the row is set.
            if (row) return deleteEquipmentRecord('rental', row.rental_income_id);
            showError('No equipment record is available to delete.');
        }

        // BONDS
        function bondCalendarDate(value) {
            // Return early if the value is missing or false.
            if (!value) return '';
            // Return early if the test check matches.
            if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
            var date = new Date(value);
            // Return early if the is na n check matches.
            if (isNaN(date.getTime())) return '';
            var parts = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit'
            }).formatToParts(date);
            var values = {};
            // Process each part.
            parts.forEach(function(part) { values[part.type] = part.value; });
            return values.year + '-' + values.month + '-' + values.day;
        }

        // Load bonds.
        function loadBonds() {
            var projectFilter = document.getElementById('bondProjectFilter').value;
            var statusFilter = document.getElementById('bondStatusFilter').value;

            var endpoint = '/construction-bonds';
            var params = [];
            var month = document.getElementById('bondMonth').value;
            // Run this step if the month is set.
            if (month) params.push('month=' + encodeURIComponent(month));

            // Run this step if the project filter is set and the project filter does not equal all.
            if (projectFilter && projectFilter !== 'all') {
                params.push('project_id=' + projectFilter);
            }
            // Run this step if the status filter is set and the status filter does not equal all.
            if (statusFilter && statusFilter !== 'all') {
                params.push('status=' + statusFilter);
            }

            // Run this step if the params length is above 0.
            if (params.length > 0) {
                endpoint += '?' + params.join('&');
            }

            // Use the result when this request finishes.
            apiFetch(endpoint)
                .then(function(data) {
                    var tbody = document.getElementById('bondBody');
                    tbody.innerHTML = '';

                    // Run this step if the data is missing or false or the data length is missing or false.
                    if (!data || !data.length) {
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;">No bonds found.</td></tr>';
                        return;
                    }

                    var total = 0;
                    // Process each row.
                    data.forEach(function(row) {
                        var tr = document.createElement('tr');
                        var statusClass = 'status-' + (row.status || 'active');
                        
                        var bondDate = bondCalendarDate(row.bond_date);
                        var dateFormatted = bondDate ? new Date(bondDate + 'T00:00:00').toLocaleDateString('en-US', {
                            year: 'numeric', month: '2-digit', day: '2-digit'
                        }) : '—';
                        
                        var projectName = row.project ? row.project.project_name : (row.project_name || 'Project ' + row.project_id);
                        
                        tr.setAttribute('data-bond-id', row.bond_id);
                        tr.setAttribute('data-project-id', row.project_id);
                        tr.setAttribute('data-project-name', projectName);
                        tr.setAttribute('data-date', bondDate);
                        tr.setAttribute('data-amount', row.amount || 0);
                        tr.setAttribute('data-provider', row.bond_provider || '');
                        tr.setAttribute('data-status', row.status || 'active');
                        tr.setAttribute('data-remarks', row.remarks || '');
                        tr.style.cursor = 'pointer';
                        tr.onclick = function() { openBondModal(this); };

                        tr.innerHTML = '<td>' + projectName + '</td>' +
                            '<td>' + dateFormatted + '</td>' +
                            '<td>' + (row.bond_provider || '—') + '</td>' +
                            '<td>' + formatCurrency(row.amount || 0) + '</td>' +
                            '<td><span class="' + statusClass + '">' + (row.status || 'active') + '</span></td>';
                        tbody.appendChild(tr);
                        total += parseFloat(row.amount) || 0;
                    });

                    var tr = document.createElement('tr');
                    tr.className = 'total-row';
                    tr.innerHTML = '<td><strong>GRAND TOTAL</strong></td><td></td><td></td>' +
                        '<td><strong>' + formatCurrency(total) + '</strong></td>' +
                        '<td></td>';
                    tbody.appendChild(tr);
                    filterFinanceRows('bondSearch', 'bondBody');
                })
                .catch(function(error) {
                    showError('Error loading Bonds: ' + error.message);
                });
        }

        // Bond detail modal
        var currentBondRow = null;
        var isBondEditMode = false;

        // Open bond modal.
        function openBondModal(row) {
            currentBondRow = row;
            document.getElementById('bondModalTitle').textContent = 'Bond Details';
            
            var projectName = row.dataset.projectName || '';
            var date = row.dataset.date || '';
            var amount = parseFloat(row.dataset.amount) || 0;
            var provider = row.dataset.provider || '';
            var status = row.dataset.status || 'active';
            var remarks = row.dataset.remarks || '';
            
            document.getElementById('bondDetailProject').textContent = projectName;
            document.getElementById('bondDetailDate').textContent = date ? new Date(date).toLocaleDateString() : '—';
            document.getElementById('bondDetailAmount').textContent = formatCurrency(amount);
            document.getElementById('bondDetailProvider').textContent = provider || '—';
            document.getElementById('bondDetailStatus').textContent = status || 'active';
            document.getElementById('bondDetailRemarks').textContent = remarks || '—';
            
            document.getElementById('bondDetailProjectEdit').value = row.dataset.projectId || '';
            document.getElementById('bondDetailDateEdit').value = date || '';
            document.getElementById('bondDetailAmountEdit').value = amount;
            document.getElementById('bondDetailProviderEdit').value = provider || '';
            document.getElementById('bondDetailStatusEdit').value = status || 'active';
            document.getElementById('bondDetailRemarksEdit').value = remarks || '';
            
            document.getElementById('bondDetailProjectDisplay').textContent = projectName;

            // Run this step if the is bond edit mode is set.
            if (isBondEditMode) toggleBondEdit();
            isBondEditMode = false;
            document.getElementById('bondDetailModal').classList.remove('is-editing');
            document.getElementById('bondDetailEditBtn').style.display = 'inline-block';
            document.getElementById('bondDetailDeleteBtn').style.display = 'none';
            document.getElementById('bondDetailSaveBtn').style.display = 'none';
            
            // Process each el.
            document.querySelectorAll('#bondDetailModal .detail-edit').forEach(function(el) { 
                el.style.display = 'none'; 
            });
            // Process each el.
            document.querySelectorAll('#bondDetailModal .detail-value').forEach(function(el) { 
                el.style.display = ''; 
            });
            document.getElementById('bondDetailProjectDisplay').style.display = 'none';

            document.getElementById('bondDetailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Close bond modal.
        function closeBondModal() {
            document.getElementById('bondDetailModal').classList.remove('active');
            document.body.style.overflow = '';
            // Run this step if the is bond edit mode is set.
            if (isBondEditMode) toggleBondEdit();
        }

        // Switch bond edit.
        function toggleBondEdit() {
            isBondEditMode = !isBondEditMode;
            var editBtn = document.getElementById('bondDetailEditBtn');
            var deleteBtn = document.getElementById('bondDetailDeleteBtn');
            var saveBtn = document.getElementById('bondDetailSaveBtn');
            
            var editableFields = ['bondDetailProjectEdit', 'bondDetailDateEdit', 'bondDetailAmountEdit', 'bondDetailProviderEdit',
                                  'bondDetailStatusEdit', 'bondDetailRemarksEdit'];
            var displayFields = ['bondDetailProject', 'bondDetailDate', 'bondDetailAmount', 
                                 'bondDetailProvider', 'bondDetailStatus', 'bondDetailRemarks'];

            // Run this step if the is bond edit mode is set.
            if (isBondEditMode) {
                document.getElementById('bondDetailModal').classList.add('is-editing');
                document.getElementById('bondModalTitle').textContent = 'Edit Construction Bond';
                editBtn.style.display = 'none';
                deleteBtn.style.display = 'inline-block';
                saveBtn.style.display = 'inline-block';
                
                // Process each id.
                displayFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    // Run this step if the el is set.
                    if (el) el.style.display = 'none';
                });
                
                // Process each id.
                editableFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    // Run this step if the el is set.
                    if (el) el.style.display = '';
                });
                
                document.getElementById('bondDetailProjectDisplay').style.display = 'none';
                
            } else {
                document.getElementById('bondDetailModal').classList.remove('is-editing');
                document.getElementById('bondModalTitle').textContent = 'Bond Details';
                editBtn.style.display = 'inline-block';
                deleteBtn.style.display = 'none';
                saveBtn.style.display = 'none';
                
                // Process each id.
                displayFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    // Run this step if the el is set.
                    if (el) el.style.display = '';
                });
                
                // Process each id.
                editableFields.forEach(function(id) {
                    var el = document.getElementById(id);
                    // Run this step if the el is set.
                    if (el) el.style.display = 'none';
                });
                
                document.getElementById('bondDetailProjectDisplay').style.display = 'none';
            }
        }

        // SUMMARY
        function loadSummary() {
            var month = document.getElementById('summaryMonth').value;
            // Return early if the month is missing or false.
            if (!month) return;
            var period = month + '-01';

            // Use the result when this request finishes.
            apiFetch('/reports/expovrall?period=' + period)
                .then(function(data) {
                    var tbody = document.getElementById('summaryBody');
                    tbody.innerHTML = '';

                    // Run this step if the data is missing or false or the data length is missing or false.
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

                    // Process each row.
                    data.forEach(function(row) {
                        var legacyCode = financeLegacyCategoryCode(row.category_code);
                        // Run this step if the legacy code is set.
                        if (categoryMapping[legacyCode]) {
                            categories[categoryMapping[legacyCode]] += parseFloat(row.category_total) || 0;
                        }
                    });

                    var total = 0;
                    // Process each cat name.
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

        // Event listeners
        document.addEventListener('click', function(e) {
            var modals = ['addExpenseModal', 'addBudgetModal', 'addContractModal',
                          'addReceivableModal', 'addCashModal', 'addRepairModal', 'addBackhoeExpenseModal',
                          'addBackhoeRentalModal', 'addBondModal', 'expenseDetailModal', 'budgetDetailModal',
                          'receivableDetailModal', 'bondDetailModal', 'cashDetailModal', 'equipmentDetailModal'];
            // Process each id.
            modals.forEach(function(id) {
                var modal = document.getElementById(id);
                // Run this step if the modal is set and the target equals the modal.
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
                        'cashDetailModal': closeCashModal,
                        'equipmentDetailModal': closeEquipmentDetailModal
                    }[id];
                    // Run this step if the close fn is set.
                    if (closeFn) closeFn();
                }
            });

            // Run this step if the style display equals block.
            if (document.getElementById('errorNotification').style.display === 'block') {
                // Run this step if the closest is missing or false.
                if (!e.target.closest('.error-notification')) closeError();
            }
            // Run this step if the style display equals block.
            if (document.getElementById('successNotification').style.display === 'block') {
                // Run this step if the closest is missing or false.
                if (!e.target.closest('.success-notification')) closeSuccess();
            }
        });

        // Init
        document.addEventListener('DOMContentLoaded', function() {
            var requestedTab = document.body.dataset.financeTab || 'expenses';
            currentReportTab = requestedTab;
            var initialLoad;
            // Choose the action for the requested tab.
            switch (requestedTab) {
                case 'budgets':
                    // Use the result when this request finishes.
                    initialLoad = fetchProjects()
                        .then(function() { return fetchExpenses(); })
                        .then(function() { return fetchBudgetData(); })
                        .then(function() { switchBudgetContractsSubtab(new URLSearchParams(window.location.search).get('subtab') === 'contracts' ? 'contracts' : 'budgets', false); });
                    break;
                case 'profit':
                case 'receivables':
                case 'bonds':
                    // Use the result when this request finishes.
                    initialLoad = fetchProjects().then(function() { switchReportTab(requestedTab); });
                    break;
                case 'backhoe':
                    // Use the result when this request finishes.
                    initialLoad = fetchProjects().then(function() { return fetchAssets(); }).then(function() { switchReportTab(requestedTab); });
                    break;
                case 'cash':
                    // Use the result when this request finishes.
                    initialLoad = fetchCashAccounts().then(function() { switchReportTab(requestedTab); });
                    break;
                case 'summary':
                    switchReportTab(requestedTab);
                    initialLoad = Promise.resolve();
                    break;
                default:
                    // Use the result when this request finishes.
                    initialLoad = fetchProjects()
                        .then(function() { return fetchExpenseCategories(); })
                        .then(function() { return fetchExpenses(); })
                        .then(function() { return fetchBudgetData(); })
                        .then(function() { return fetchAssets(); })
                        .then(function() { switchReportTab('expenses'); });
            }
            // Handle a failed request.
            initialLoad.catch(function(error) { showError(error.message || 'Unable to load this finance section.'); });
            initializeProofFileUpload();
        });

    </script>

    @include('partials.data-import', ['importModule' => 'finance'])
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/finance-analytics.js') }}?v={{ filemtime(public_path('js/finance-analytics.js')) }}"></script>
    {{-- Set up the actions used by this page section. --}}
    <script data-login-epoch="{{ session('navigation_epoch', 'initial') }}" src="{{ asset('js/pfims-system-ui.js') }}?v={{ filemtime(public_path('js/pfims-system-ui.js')) }}"></script>
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/finance-review-flow.js') }}"></script>
    @include('partials.validation-alert')
</body>
</html>
