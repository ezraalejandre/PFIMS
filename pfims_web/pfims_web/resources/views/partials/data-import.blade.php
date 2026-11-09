{{-- Prepare the values used by this page section. --}}
@php
    $isInventoryImport = ($importModule ?? '') === 'inventory';
    $isProjectImport = ($importModule ?? '') === 'projects';
    $importTitle = $isInventoryImport ? 'Inventory Data' : ($isProjectImport ? 'Projects' : 'Finance Expenses');
@endphp
{{-- Show the pfims import modal section. --}}
<div id="pfimsImportModal" class="pfims-import-overlay" data-import-module="{{ $importModule ?? '' }}" hidden>
    {{-- Show the page section. --}}
    <section class="pfims-import-card" role="dialog" aria-modal="true" aria-labelledby="pfimsImportTitle">
        <div class="pfims-import-titlebar">
            <div>
                <h2 id="pfimsImportTitle">Import {{ $importTitle }}</h2>
                <p>CSV and XLSX files up to 5 MB and 10,000 data rows. Imports are all-or-nothing.</p>
            </div>
            <button type="button" class="pfims-import-close" onclick="closePfimsImport()" aria-label="Close">&times;</button>
        </div>
        {{-- Collect the details entered in this form. --}}
        <form id="pfimsImportForm">
            {{-- Show this section when its display check passes. --}}
            @if($isInventoryImport)
                <input id="pfimsImportType" name="type" type="hidden" value="items">
            @endif
            <label class="pfims-import-field">
                <span>Import file</span>
                <input id="pfimsImportFile" name="file" type="file" accept=".csv,.xlsx" required>
            </label>
            {{-- Show the pfims import guidance section. --}}
            <div class="pfims-import-guidance" id="pfimsImportGuidance"></div>
            <a id="pfimsImportTemplate" class="pfims-template-link" href="#" download>Download CSV template</a>
            {{-- Show the pfims import errors section. --}}
            <div id="pfimsImportErrors" class="pfims-import-errors" hidden></div>
            <div class="pfims-import-actions">
                <button type="button" class="{{ $isProjectImport ? 'btn-cancel pfims-project-import-cancel' : 'btn-clear-search' }}" onclick="closePfimsImport()">Cancel</button>
                <button id="pfimsImportSubmit" type="submit" class="btn-add-data gold">Validate &amp; Import</button>
            </div>
        </form>
    </section>
</div>

{{-- Set the styles used by this page section. --}}
<style>
    /* Style the pfims import overlay. */
    .pfims-import-overlay[hidden] { display: none !important; }
    /* Style the pfims import overlay. */
    .pfims-import-overlay { position: fixed; inset: 0; z-index: 12000; display: flex; align-items: center; justify-content: center; padding: 20px; background: rgba(8,18,28,.62); backdrop-filter: blur(3px); }
    /* Style the pfims import card. */
    .pfims-import-card { width: min(620px, 100%); max-height: 90vh; overflow-y: auto; background: #fff; border-radius: 16px; padding: 26px; box-shadow: 0 24px 70px rgba(0,0,0,.3); }
    /* Style the pfims import titlebar. */
    .pfims-import-titlebar { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; margin-bottom: 20px; }
    /* Style the pfims import titlebar. */
    .pfims-import-titlebar h2 { margin: 0 0 4px; color: #1a2b3c; font-size: 1.35rem; }
    /* Style the pfims import titlebar. */
    .pfims-import-titlebar p { margin: 0; color: #6f7780; font-size: .86rem; }
    /* Style the pfims import close. */
    .pfims-import-close { border: 0; background: transparent; color: #777; font-size: 1.8rem; line-height: 1; cursor: pointer; }
    /* Style the pfims import field. */
    .pfims-import-field { display: grid; gap: 7px; margin-bottom: 16px; color: #333; font-size: .88rem; font-weight: 600; }
    /* Style the pfims import field. */
    .pfims-import-field input, .pfims-import-field select { width: 100%; min-width: 0; padding: 11px 12px; border: 1px solid #d8dce0; border-radius: 8px; background: #fff; color: #222; }
    /* Style the pfims import guidance. */
    .pfims-import-guidance { padding: 13px 15px; border-radius: 9px; background: #f7f4ee; color: #4e5964; font-size: .82rem; line-height: 1.55; word-break: break-word; }
    /* Style the pfims template link. */
    .pfims-template-link { display: inline-block; margin: 13px 0 6px; color: #8b6c34; font-weight: 600; font-size: .86rem; }
    /* Style the pfims import errors. */
    .pfims-import-errors { margin-top: 12px; padding: 12px 14px; max-height: 210px; overflow: auto; border: 1px solid #efb4b4; border-radius: 8px; background: #fff4f4; color: #982c2c; font-size: .8rem; }
    /* Style the pfims import errors. */
    .pfims-import-errors ul { margin: 8px 0 0 18px; padding: 0; }
    /* Style the pfims import actions. */
    .pfims-import-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 18px; border-top: 1px solid #eee; }
    /* Style the pfims import actions btn add data. */
    .pfims-import-actions .btn-add-data { border: 0; border-radius: 7px; padding: 9px 16px; background: #e19a45; color: #fff; font-weight: 600; cursor: pointer; }
    /* Style the pfims import actions btn add data on hover. */
    .pfims-import-actions .btn-add-data:hover { background: #cf8735; }
    /* Style the pfims import actions btn clear search. */
    .pfims-import-actions .btn-clear-search { border: 1px solid #d8dce0; border-radius: 7px; padding: 9px 16px; background: #fff; color: #555; font-weight: 600; cursor: pointer; }
    /* Style the pfims import actions pfims project import cancel. */
    .pfims-import-actions .pfims-project-import-cancel { min-width: 104px; padding: 10px 22px; border: 1px solid #d8dce0; border-radius: 8px; background: #fff; color: #64748b; font-size: .9rem; font-weight: 700; cursor: pointer; transition: border-color .2s ease, background .2s ease, color .2s ease, box-shadow .2s ease; }
    /* Style the pfims import actions pfims project import cancel on hover. */
    .pfims-import-actions .pfims-project-import-cancel:hover { border-color: #c5cbd2; background: #f5f5f5; color: #1a2b3c; box-shadow: 0 2px 6px rgba(15, 23, 42, .08); }
    /* Style the pfims import actions pfims project import cancel when focused. */
    .pfims-import-actions .pfims-project-import-cancel:focus-visible { outline: 3px solid rgba(225, 154, 69, .25); outline-offset: 2px; }

    /* Style the pfims import card. */
    html[data-theme="dark"] .pfims-import-card {
        background: #111827;
        color: #edf2f7;
        box-shadow: 0 24px 70px rgba(0,0,0,.5);
    }
    /* Style the pfims import titlebar. */
    html[data-theme="dark"] .pfims-import-titlebar h2 { color: #f8fafc; }
    /* Style the pfims import titlebar. */
    html[data-theme="dark"] .pfims-import-titlebar p { color: #b7c3d4; }
    /* Style the pfims import close. */
    html[data-theme="dark"] .pfims-import-close { color: #c2cddd; }
    /* Style the pfims import field. */
    html[data-theme="dark"] .pfims-import-field { color: #edf2f7; }
    /* Style the pfims import field. */
    html[data-theme="dark"] .pfims-import-field input,
    html[data-theme="dark"] .pfims-import-field select {
        background: #0b1220;
        color: #f8fafc;
        border-color: #46546a;
    }
    /* Style the pfims import guidance. */
    html[data-theme="dark"] .pfims-import-guidance {
        background: #182235;
        color: #c2cddd;
    }
    /* Style the pfims template link. */
    html[data-theme="dark"] .pfims-template-link { color: #f3a64f; }
    /* Style the pfims import errors. */
    html[data-theme="dark"] .pfims-import-errors {
        background: #3a1f1f;
        border-color: #6b3a3a;
        color: #ff8b91;
    }
    /* Style the pfims import actions. */
    html[data-theme="dark"] .pfims-import-actions { border-top-color: #2c3749; }
    /* Style the pfims import actions btn add data. */
    html[data-theme="dark"] .pfims-import-actions .btn-add-data {
        background: #cf8735;
        color: #ffffff;
    }
    /* Style the pfims import actions btn add data on hover. */
    html[data-theme="dark"] .pfims-import-actions .btn-add-data:hover { background: #b8752c; }
    /* Style the pfims import actions btn clear search. */
    html[data-theme="dark"] .pfims-import-actions .btn-clear-search {
        background: #263349;
        color: #f1f5f9;
        border-color: #46546a;
    }
    /* Style the pfims import actions btn clear search on hover. */
    html[data-theme="dark"] .pfims-import-actions .btn-clear-search:hover { background: #34435b; }
    /* Style the pfims import actions pfims project import cancel. */
    html[data-theme="dark"] .pfims-import-actions .pfims-project-import-cancel { background: #263349; color: #f1f5f9; border-color: #46546a; }
    /* Style the pfims import actions pfims project import cancel on hover. */
    html[data-theme="dark"] .pfims-import-actions .pfims-project-import-cancel:hover { background: #34435b; color: #fff; border-color: #5b6b82; }

    /* Adjust these styles for this screen size or display setting. */
    @media (max-width: 600px) {
        /* Style the pfims import overlay. */
        .pfims-import-overlay { align-items: flex-start; padding: 10px; overflow-y: auto; }
        /* Style the pfims import card. */
        .pfims-import-card { width: 100%; max-height: calc(100dvh - 20px); padding: 18px; border-radius: 14px; }
        /* Style the pfims import titlebar. */
        .pfims-import-titlebar { gap: 12px; margin-bottom: 16px; }
        /* Style the pfims import titlebar. */
        .pfims-import-titlebar h2 { font-size: 1.15rem; }
        /* Style the pfims import actions. */
        .pfims-import-actions > button { flex: 1 1 0; min-width: 0; }
    }
</style>

{{-- Set up the actions used by this page section. --}}
<script>
    // Set up the data import page actions.
    (function () {
        var modal = document.getElementById('pfimsImportModal');
        var moduleName = modal.dataset.importModule || '';
        var form = document.getElementById('pfimsImportForm');
        var errors = document.getElementById('pfimsImportErrors');
        var submit = document.getElementById('pfimsImportSubmit');
        var financeImportType = 'finance-expenses';

        window.openPfimsImport = function (type) {
            form.reset();
            // Run this step if the module name equals finance.
            if (moduleName === 'finance') {
                financeImportType = type === 'contracts' ? 'contracts' : 'finance-expenses';
                document.getElementById('pfimsImportTitle').textContent = financeImportType === 'contracts' ? 'Import Contracts' : 'Import Expenses';
            }
            // Run this step if the module name equals inventory.
            if (moduleName === 'inventory') {
                var selectedType = type === 'transactions' ? 'transactions' : 'items';
                document.getElementById('pfimsImportType').value = selectedType;
                document.getElementById('pfimsImportTitle').textContent = selectedType === 'items' ? 'Import Items' : 'Import Transactions';
            }
            errors.hidden = true;
            errors.innerHTML = '';
            updatePfimsImportGuidance();
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
        };
        window.closePfimsImport = function () {
            modal.hidden = true;
            document.body.style.overflow = '';
        };
        window.updatePfimsImportGuidance = function () {
            var type = moduleName === 'inventory' ? document.getElementById('pfimsImportType').value : (moduleName === 'projects' ? 'projects' : financeImportType);
            var contracts = {
                'contracts': 'Required columns: project_name, additional_works_contract, original_payment_received, additional_works_payment, remarks. Project must have a positive budget and no existing contract. Contract Price uses its budget. Amounts must be zero or greater; remarks may be blank.',
                'projects': 'Required: project_name, client_name, project_manager, start_date, estimated_end_date, actual_end_date, worker_count, phase, status, budget. Only actual_end_date may be blank. Phase must already exist in Settings; completion is calculated automatically.',
                'finance-expenses': 'Required with no blank cells: project_name, category_code, project_cost_component, expense_description, amount, expense_date, remarks. Project cost component must be material, labor, equipment, or other.',
                'items': 'Required with no blank cells: item_name, category, supplier, unit, unit_price, current_stock, reorder_level, opening_balance_date. Category, supplier, and unit names must already exist in Settings.',
                'transactions': 'Required: item_name, project_name, transaction_type, quantity, bar_code, transaction_date, stock_in_reason. Use For Storage for IN and N/A reason for OUT. Purchase IN uses the selected item Unit Price automatically; no amount column is needed. OUT requires a real project and is checked against available stock in file order.'
            };
            var templateType = type === 'items' ? 'inventory-items' : (type === 'transactions' ? 'inventory-transactions' : type);
            document.getElementById('pfimsImportGuidance').textContent = contracts[type];
            document.getElementById('pfimsImportTemplate').href = '/api/imports/templates/' + templateType;
        };

        // Handle the click event.
        modal.addEventListener('click', function (event) {
            // Run this step if the target equals the modal.
            if (event.target === modal) closePfimsImport();
        });
        // Handle the keydown event.
        document.addEventListener('keydown', function (event) {
            // Run this step if the key equals Escape and the hidden is missing or false.
            if (event.key === 'Escape' && !modal.hidden) closePfimsImport();
        });
        // Handle the submit event.
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var file = document.getElementById('pfimsImportFile').files[0];
            // Return early if the file is missing or false.
            if (!file) return;
            // Run this step if the size is above the value.
            if (file.size > 5 * 1024 * 1024) {
                renderPfimsImportErrors('The selected file exceeds 5 MB.', []);
                return;
            }

            var extension = (file.name.split('.').pop() || '').toLowerCase();
            // Run this step if the index of equals the value.
            if (['csv', 'xlsx'].indexOf(extension) === -1) {
                renderPfimsImportErrors('Select a CSV or XLSX file.', []);
                return;
            }

            var payload = new FormData(form);
            var endpoint = moduleName === 'inventory' ? '/api/imports/inventory' : (moduleName === 'projects' ? '/api/imports/projects' : '/api/imports/' + financeImportType);
            submit.disabled = true;
            submit.textContent = 'Validating...';
            errors.hidden = true;

            // Use the result when this request finishes.
            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: payload
            }).then(function (response) {
                // Use the result when this request finishes.
                return response.json().then(function (data) { return { ok: response.ok, data: data }; });
            }).then(function (result) {
                // Run this step if the result ok is missing or false.
                if (!result.ok) {
                    var validation = result.data.errors || [];
                    // Run this step if the is array is missing or false.
                    if (!Array.isArray(validation)) {
                        // Run the flat map action.
                        validation = Object.keys(validation).flatMap(function (key) { return validation[key]; });
                    }
                    renderPfimsImportErrors(result.data.message || 'Import validation failed.', validation);
                    return;
                }
                closePfimsImport();
                // Run this step if the show success equals function.
                if (typeof showSuccess === 'function') showSuccess(result.data.message);
                // Run this step if the module name equals inventory.
                if (moduleName === 'inventory') {
                    // Run this step if the load inventory items equals function.
                    if (typeof loadInventoryItems === 'function') loadInventoryItems();
                }
                // Run this step if the module name equals finance and the finance import type equals contracts and the load profit equals function.
                if (moduleName === 'finance' && financeImportType === 'contracts' && typeof loadProfit === 'function') loadProfit();
                else if (moduleName === 'finance' && typeof fetchExpenses === 'function') fetchExpenses();
                // Run this step if the module name equals projects and the fetch projects equals function.
                if (moduleName === 'projects' && typeof fetchProjects === 'function') fetchProjects();
            }).catch(function () {
                renderPfimsImportErrors('The import request could not be completed.', []);
            }).finally(function () {
                submit.disabled = false;
                submit.textContent = 'Validate & Import';
            });
        });

        // Show pfims import errors.
        function renderPfimsImportErrors(message, rowErrors) {
            var html = '<strong>' + escapePfimsImport(message) + '</strong>';
            // Run this step if the row errors length is set.
            if (rowErrors.length) {
                // Build a value for each entry.
                html += '<ul>' + rowErrors.slice(0, 100).map(function (entry) {
                    // Return early if the entry equals string.
                    if (typeof entry === 'string') return '<li>' + escapePfimsImport(entry) + '</li>';
                    return '<li>Row ' + escapePfimsImport(entry.row) + ', ' + escapePfimsImport(entry.field) + ': ' + escapePfimsImport(entry.message) + '</li>';
                }).join('') + '</ul>';
            }
            errors.innerHTML = html;
            errors.hidden = false;
        }
        // Get the escape pfims import.
        function escapePfimsImport(value) {
            var element = document.createElement('div');
            element.textContent = value == null ? '' : String(value);
            return element.innerHTML;
        }
        updatePfimsImportGuidance();
    })();
</script>
