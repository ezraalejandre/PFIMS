@php($isInventoryImport = ($importModule ?? '') === 'inventory')
<div id="pfimsImportModal" class="pfims-import-overlay" data-import-module="{{ $importModule ?? '' }}" hidden>
    <section class="pfims-import-card" role="dialog" aria-modal="true" aria-labelledby="pfimsImportTitle">
        <div class="pfims-import-titlebar">
            <div>
                <h2 id="pfimsImportTitle">Import {{ $isInventoryImport ? 'Inventory Data' : 'Finance Expenses' }}</h2>
                <p>CSV and XLSX files up to 5 MB. Imports are all-or-nothing.</p>
            </div>
            <button type="button" class="pfims-import-close" onclick="closePfimsImport()" aria-label="Close">&times;</button>
        </div>
        <form id="pfimsImportForm">
            @if($isInventoryImport)
                <label class="pfims-import-field">
                    <span>Data type</span>
                    <select id="pfimsImportType" name="type" onchange="updatePfimsImportGuidance()" required>
                        <option value="items">Inventory items and opening balances</option>
                        <option value="transactions">Inventory stock movements</option>
                    </select>
                </label>
            @endif
            <label class="pfims-import-field">
                <span>Import file</span>
                <input id="pfimsImportFile" name="file" type="file" accept=".csv,.xlsx" required>
            </label>
            <div class="pfims-import-guidance" id="pfimsImportGuidance"></div>
            <a id="pfimsImportTemplate" class="pfims-template-link" href="#">Download CSV template</a>
            <div id="pfimsImportErrors" class="pfims-import-errors" hidden></div>
            <div class="pfims-import-actions">
                <button type="button" class="btn-clear-search" onclick="closePfimsImport()">Cancel</button>
                <button id="pfimsImportSubmit" type="submit" class="btn-add-data gold">Validate &amp; Import</button>
            </div>
        </form>
    </section>
</div>

<style>
    .pfims-import-overlay[hidden] { display: none !important; }
    .pfims-import-overlay { position: fixed; inset: 0; z-index: 12000; display: flex; align-items: center; justify-content: center; padding: 20px; background: rgba(8,18,28,.62); backdrop-filter: blur(3px); }
    .pfims-import-card { width: min(620px, 100%); max-height: 90vh; overflow-y: auto; background: #fff; border-radius: 16px; padding: 26px; box-shadow: 0 24px 70px rgba(0,0,0,.3); }
    .pfims-import-titlebar { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; margin-bottom: 20px; }
    .pfims-import-titlebar h2 { margin: 0 0 4px; color: #1a2b3c; font-size: 1.35rem; }
    .pfims-import-titlebar p { margin: 0; color: #6f7780; font-size: .86rem; }
    .pfims-import-close { border: 0; background: transparent; color: #777; font-size: 1.8rem; line-height: 1; cursor: pointer; }
    .pfims-import-field { display: grid; gap: 7px; margin-bottom: 16px; color: #333; font-size: .88rem; font-weight: 600; }
    .pfims-import-field input, .pfims-import-field select { width: 100%; padding: 11px 12px; border: 1px solid #d8dce0; border-radius: 8px; background: #fff; color: #222; }
    .pfims-import-guidance { padding: 13px 15px; border-radius: 9px; background: #f7f4ee; color: #4e5964; font-size: .82rem; line-height: 1.55; word-break: break-word; }
    .pfims-template-link { display: inline-block; margin: 13px 0 6px; color: #8b6c34; font-weight: 600; font-size: .86rem; }
    .pfims-import-errors { margin-top: 12px; padding: 12px 14px; max-height: 210px; overflow: auto; border: 1px solid #efb4b4; border-radius: 8px; background: #fff4f4; color: #982c2c; font-size: .8rem; }
    .pfims-import-errors ul { margin: 8px 0 0 18px; padding: 0; }
    .pfims-import-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 18px; border-top: 1px solid #eee; }
    .pfims-import-actions .btn-add-data { border: 0; border-radius: 7px; padding: 9px 16px; background: #c9a96e; color: #fff; font-weight: 600; cursor: pointer; }
    .pfims-import-actions .btn-clear-search { border: 1px solid #d8dce0; border-radius: 7px; padding: 9px 16px; background: #fff; color: #555; font-weight: 600; cursor: pointer; }
</style>

<script>
    (function () {
        var modal = document.getElementById('pfimsImportModal');
        var moduleName = modal.dataset.importModule || '';
        var form = document.getElementById('pfimsImportForm');
        var errors = document.getElementById('pfimsImportErrors');
        var submit = document.getElementById('pfimsImportSubmit');

        window.openPfimsImport = function () {
            form.reset();
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
            var type = moduleName === 'inventory' ? document.getElementById('pfimsImportType').value : 'finance-expenses';
            var contracts = {
                'finance-expenses': 'Required headers: category_code, expense_description, amount, expense_date. Optional: project_name, project_cost_component, remarks. Project/direct expenses need project_cost_component: material, labor, equipment, or other.',
                'items': 'Required headers: item_name, category, supplier, unit, current_stock, reorder_level. Optional: opening_balance_date. Category, supplier, and unit names must already exist in Settings.',
                'transactions': 'Required headers: item_name, transaction_type, quantity, transaction_date. Optional: project_name, bar_code. OUT rows are checked against available stock in file order.'
            };
            var templateType = type === 'items' ? 'inventory-items' : (type === 'transactions' ? 'inventory-transactions' : 'finance-expenses');
            document.getElementById('pfimsImportGuidance').textContent = contracts[type];
            document.getElementById('pfimsImportTemplate').href = '/api/imports/templates/' + templateType;
        };

        modal.addEventListener('click', function (event) {
            if (event.target === modal) closePfimsImport();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) closePfimsImport();
        });
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var file = document.getElementById('pfimsImportFile').files[0];
            if (!file) return;
            if (file.size > 5 * 1024 * 1024) {
                renderPfimsImportErrors('The selected file exceeds 5 MB.', []);
                return;
            }

            var extension = (file.name.split('.').pop() || '').toLowerCase();
            if (['csv', 'xlsx'].indexOf(extension) === -1) {
                renderPfimsImportErrors('Select a CSV or XLSX file.', []);
                return;
            }

            var payload = new FormData(form);
            var endpoint = moduleName === 'inventory' ? '/api/imports/inventory' : '/api/imports/finance-expenses';
            submit.disabled = true;
            submit.textContent = 'Validating...';
            errors.hidden = true;

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: payload
            }).then(function (response) {
                return response.json().then(function (data) { return { ok: response.ok, data: data }; });
            }).then(function (result) {
                if (!result.ok) {
                    var validation = result.data.errors || [];
                    if (!Array.isArray(validation)) {
                        validation = Object.keys(validation).flatMap(function (key) { return validation[key]; });
                    }
                    renderPfimsImportErrors(result.data.message || 'Import validation failed.', validation);
                    return;
                }
                closePfimsImport();
                if (typeof showSuccess === 'function') showSuccess(result.data.message);
                if (moduleName === 'inventory' && typeof loadInventoryItems === 'function') loadInventoryItems();
                if (moduleName === 'finance' && typeof fetchExpenses === 'function') fetchExpenses();
            }).catch(function () {
                renderPfimsImportErrors('The import request could not be completed.', []);
            }).finally(function () {
                submit.disabled = false;
                submit.textContent = 'Validate & Import';
            });
        });

        function renderPfimsImportErrors(message, rowErrors) {
            var html = '<strong>' + escapePfimsImport(message) + '</strong>';
            if (rowErrors.length) {
                html += '<ul>' + rowErrors.slice(0, 100).map(function (entry) {
                    if (typeof entry === 'string') return '<li>' + escapePfimsImport(entry) + '</li>';
                    return '<li>Row ' + escapePfimsImport(entry.row) + ', ' + escapePfimsImport(entry.field) + ': ' + escapePfimsImport(entry.message) + '</li>';
                }).join('') + '</ul>';
            }
            errors.innerHTML = html;
            errors.hidden = false;
        }
        function escapePfimsImport(value) {
            var element = document.createElement('div');
            element.textContent = value == null ? '' : String(value);
            return element.innerHTML;
        }
        updatePfimsImportGuidance();
    })();
</script>
