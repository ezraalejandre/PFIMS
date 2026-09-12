(function () {
    'use strict';

    var flows = {
        inventoryExpenseModal: ['inventoryExpenseAmount'],
        addExpenseModal: ['expenseDesc', 'expenseCategory', 'expenseDate'],
        addBudgetModal: ['budgetProject', 'budgetAmount'],
        addContractModal: ['contractProject'],
        addReceivableModal: ['receivableEntryType', 'receivableCounterparty', 'receivableDate'],
        addCashModal: ['cashAccount', 'cashPeriod', 'cashBalance'],
        addRepairModal: ['repairAssetSelect', 'repairExpenseType', 'repairAmount', 'repairDate'],
        addBackhoeExpenseModal: ['backhoeExpenseAsset', 'backhoeExpenseType', 'backhoeExpenseAmount', 'backhoeExpenseDate'],
        addBackhoeRentalModal: ['backhoeRentalAsset', 'backhoeRentalPeriod', 'backhoeRentalAmount'],
        addBondModal: ['bondProject', 'bondDate', 'bondAmount']
    };

    function fieldLabel(control) {
        var group = control.closest('.form-group');
        var label = group && group.querySelector('label');
        return ((label && label.textContent) || control.getAttribute('aria-label') || control.id)
            .replace('*', '').replace(/\s+/g, ' ').trim();
    }

    function fieldValue(control) {
        if (control.type === 'file') return control.files && control.files[0] ? control.files[0].name : '';
        if (control.tagName === 'SELECT') return control.selectedOptions[0] ? control.selectedOptions[0].textContent.trim() : '';
        return String(control.value || '').trim();
    }

    function validate(flow, modal) {
        var missing = [];
        flow.required.forEach(function (id) {
            var control = document.getElementById(id);
            if (id === 'contractProject' && modal.getAttribute('data-project-id')) return;
            if (!control || !fieldValue(control) || (control.type === 'number' && Number(control.value) <= 0)) missing.push(control);
        });
        if (modal.id === 'addExpenseModal') {
            var category = document.getElementById('expenseCategory');
            var amount = document.getElementById('expenseAmount');
            var dynamic = document.getElementById('dynamicAmountFields');
            var dynamicTotal = ['expenseLaborAmount', 'expenseMaterialAmount', 'expenseEquipmentAmount', 'expenseOtherAmount']
                .reduce(function (total, id) { return total + Number(document.getElementById(id)?.value || 0); }, 0);
            if (category && category.value && ((dynamic && dynamic.style.display !== 'none') ? dynamicTotal <= 0 : Number(amount && amount.value || 0) <= 0)) {
                missing.push(amount);
            }
        }
        if (modal.id === 'addReceivableModal') {
            var agingTotal = ['receivable30d', 'receivable60d', 'receivable90d', 'receivable120d']
                .reduce(function (total, id) { return total + Number(document.getElementById(id)?.value || 0); }, 0);
            if (agingTotal <= 0) missing.push(document.getElementById('receivable30d'));
        }
        if (!missing.length) return true;
        var first = missing.find(Boolean);
        var message = first ? fieldLabel(first) + ' is required and must contain a valid value.' : 'Please complete all required fields.';
        if (typeof window.showError === 'function') window.showError(message); else window.alert(message);
        if (first) first.focus();
        return false;
    }

    function reviewRows(modal) {
        var rows = [];
        modal.querySelectorAll('.modal-body input, .modal-body select, .modal-body textarea').forEach(function (control) {
            if (control.closest('.pfims-review-panel') || control.disabled || control.type === 'hidden') return;
            var group = control.closest('.form-group');
            if (group && getComputedStyle(group).display === 'none') return;
            var value = fieldValue(control);
            if (!value || /^(select|no file chosen)/i.test(value)) return;
            rows.push([fieldLabel(control), value]);
        });
        return rows;
    }

    function setMode(flow, review) {
        flow.mode = review ? 'review' : 'details';
        flow.details.forEach(function (element) { element.hidden = review; });
        flow.panel.hidden = !review;
        flow.detailsTab.classList.toggle('active', !review);
        flow.reviewTab.classList.toggle('active', review);
        flow.save.textContent = review ? 'Confirm and save' : flow.originalSaveText;
        if (review) {
            flow.list.replaceChildren();
            reviewRows(flow.modal).forEach(function (entry) {
                var item = document.createElement('div');
                item.className = 'pfims-review-item';
                var label = document.createElement('span');
                label.textContent = entry[0];
                var value = document.createElement('strong');
                value.textContent = entry[1];
                item.append(label, value);
                flow.list.appendChild(item);
            });
        }
    }

    function install(modal) {
        if (!modal || modal.dataset.reviewFlow === 'ready') return;
        var save = Array.from(modal.querySelectorAll('.modal-footer button')).find(function (button) {
            return /save|add/i.test((button.getAttribute('onclick') || '') + ' ' + (button.textContent || ''))
                && !/delete|cancel/i.test((button.getAttribute('onclick') || '') + ' ' + (button.textContent || ''));
        });
        var body = modal.querySelector('.modal-body');
        var header = modal.querySelector('.modal-header');
        if (!save || !body || !header) return;

        var tabs = document.createElement('div');
        tabs.className = 'pfims-review-tabs';
        var detailsTab = document.createElement('button');
        detailsTab.type = 'button';
        detailsTab.className = 'active';
        detailsTab.textContent = '1. Details';
        var reviewTab = document.createElement('button');
        reviewTab.type = 'button';
        reviewTab.textContent = '2. Review';
        tabs.append(detailsTab, reviewTab);
        header.insertAdjacentElement('afterend', tabs);

        var panel = document.createElement('section');
        panel.className = 'pfims-review-panel';
        panel.hidden = true;
        panel.innerHTML = '<h3>Review entry</h3><p>Check these details before saving.</p><div class="pfims-review-list"></div>';
        var details = Array.from(body.children);
        body.appendChild(panel);
        var flow = {
            modal: modal, required: flows[modal.id], save: save, originalSaveText: save.textContent.trim(),
            body: body, details: details, panel: panel, list: panel.querySelector('.pfims-review-list'),
            detailsTab: detailsTab, reviewTab: reviewTab, mode: 'details'
        };
        modal.dataset.reviewFlow = 'ready';

        detailsTab.addEventListener('click', function () { setMode(flow, false); });
        reviewTab.addEventListener('click', function () { if (validate(flow, modal)) setMode(flow, true); });
        save.addEventListener('click', function (event) {
            if (flow.mode === 'review') return;
            event.preventDefault();
            event.stopImmediatePropagation();
            if (validate(flow, modal)) setMode(flow, true);
        }, true);
        modal.addEventListener('click', function (event) {
            if (event.target === modal || event.target.closest('.modal-close, .btn-cancel')) {
                window.setTimeout(function () { setMode(flow, false); }, 0);
            }
        });
        new MutationObserver(function () {
            if (!modal.classList.contains('active')) setMode(flow, false);
        }).observe(modal, { attributes: true, attributeFilter: ['class', 'style'] });
    }

    function initialize() {
        Object.keys(flows).forEach(function (id) { install(document.getElementById(id)); });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
})();
