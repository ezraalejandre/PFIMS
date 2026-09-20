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

    function isAddMode(modal) {
        return !modal.classList.contains('is-editing') && !modal.getAttribute('data-edit-id');
    }

    function setMode(flow, review) {
        var rows = review ? reviewRows(flow.modal) : [];
        flow.mode = review ? 'review' : 'details';
        flow.details.forEach(function (element) { element.hidden = review; });
        flow.panel.hidden = !review;
        flow.detailsStep.classList.toggle('active', !review);
        flow.detailsStep.classList.toggle('completed', review);
        flow.reviewStep.classList.toggle('active', review);
        flow.back.hidden = !review;
        flow.save.classList.toggle('btn-continue', !review);
        flow.save.textContent = review ? flow.originalSaveText : 'Continue';
        if (review) {
            flow.list.replaceChildren();
            rows.forEach(function (entry) {
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

        var stepper = document.createElement('div');
        stepper.className = 'step-indicator pfims-finance-stepper';
        stepper.setAttribute('aria-label', 'Add entry progress');
        var detailsStep = document.createElement('span');
        detailsStep.className = 'step active';
        detailsStep.innerHTML = '<span class="step-number">1</span> Details';
        var reviewStep = document.createElement('span');
        reviewStep.className = 'step';
        reviewStep.innerHTML = '<span class="step-number">2</span> Review';
        stepper.append(detailsStep, reviewStep);
        header.insertAdjacentElement('afterend', stepper);

        var panel = document.createElement('section');
        panel.className = 'pfims-review-panel modal-step';
        panel.hidden = true;
        panel.innerHTML = '<h3>Review entry details</h3><div class="summary-list pfims-review-list"></div>';
        var details = Array.from(body.children);
        body.appendChild(panel);
        var footer = save.closest('.modal-footer');
        var back = document.createElement('button');
        back.type = 'button';
        back.className = 'btn-back pfims-review-back';
        back.textContent = 'Back';
        back.hidden = true;
        footer.insertBefore(back, save);
        var flow = {
            modal: modal, required: flows[modal.id], save: save, originalSaveText: save.textContent.trim(),
            body: body, details: details, panel: panel, list: panel.querySelector('.pfims-review-list'),
            stepper: stepper, detailsStep: detailsStep, reviewStep: reviewStep, back: back, mode: 'details'
        };
        modal.dataset.reviewFlow = 'ready';

        back.addEventListener('click', function () { setMode(flow, false); });
        save.addEventListener('click', function (event) {
            if (!isAddMode(modal)) return;
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
        function syncFlow() {
            var available = isAddMode(modal);
            stepper.hidden = !available;
            back.hidden = !available || flow.mode !== 'review';
            if (!available) {
                flow.details.forEach(function (element) { element.hidden = false; });
                flow.panel.hidden = true;
                save.classList.remove('btn-continue');
                save.textContent = flow.originalSaveText;
            } else if (!modal.classList.contains('active')) {
                setMode(flow, false);
            }
        }
        new MutationObserver(syncFlow).observe(modal, {
            attributes: true,
            attributeFilter: ['class', 'style', 'data-edit-id']
        });
        setMode(flow, false);
    }

    function initialize() {
        Object.keys(flows).forEach(function (id) { install(document.getElementById(id)); });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
})();
