// Set up the finance review flow page actions.
(function () {
    'use strict';

    var flows = {
        inventoryExpenseModal: ['inventoryExpenseAmount'],
        addExpenseModal: ['expenseType', 'expenseCategory', 'expenseProject', 'expenseAmount', 'expenseDate', 'expenseDesc'],
        addBudgetModal: ['budgetProject', 'budgetAmount'],
        addContractModal: ['contractProject'],
        addReceivableModal: ['receivableEntryType', 'receivableCounterparty', 'receivableDate'],
        addCashModal: ['cashAccount', 'cashPeriod', 'cashBalance'],
        addRepairModal: ['repairAssetSelect', 'repairExpenseType', 'repairAmount', 'repairDate'],
        addBackhoeExpenseModal: ['backhoeExpenseAsset', 'backhoeExpenseType', 'backhoeExpenseAmount', 'backhoeExpenseDate'],
        addBackhoeRentalModal: ['backhoeRentalAsset', 'backhoeRentalPeriod', 'backhoeRentalAmount'],
        addBondModal: ['bondProject', 'bondDate', 'bondAmount']
    };

    // Read the field label.
    function fieldLabel(control) {
        var group = control.closest('.form-group');
        var label = group && group.querySelector('label');
        return ((label && label.textContent) || control.getAttribute('aria-label') || control.id)
            .replace('*', '').replace(/\s+/g, ' ').trim();
    }

    // Read the field value.
    function fieldValue(control) {
        // Return early if the control type equals file.
        if (control.type === 'file') return control.files && control.files[0] ? control.files[0].name : '';
        // Return early if the control tag name equals SELECT.
        if (control.tagName === 'SELECT') return control.selectedOptions[0] ? control.selectedOptions[0].textContent.trim() : '';
        return String(control.value || '').trim();
    }

    // Check the required fields.
    function validate(flow, modal) {
        var missing = [];
        // Process each id.
        flow.required.forEach(function (id) {
            var control = document.getElementById(id);
            // Return early if the id equals contractProject and the get attribute check matches.
            if (id === 'contractProject' && modal.getAttribute('data-project-id')) return;
            // Run this step if the value checks match.
            if (!control || !fieldValue(control) || (control.type === 'number' && Number(control.value) <= 0)) missing.push(control);
        });
        // Run this step if the modal id equals addReceivableModal.
        if (modal.id === 'addReceivableModal') {
            // Combine the items into one result.
            var agingTotal = ['receivable30d', 'receivable60d', 'receivable90d', 'receivable120d']
                .reduce(function (total, id) { return total + Number(document.getElementById(id)?.value || 0); }, 0);
            // Run this step if the aging total is at most 0.
            if (agingTotal <= 0) missing.push(document.getElementById('receivable30d'));
        }
        // Return early if the missing length is missing or false.
        if (!missing.length) return true;
        var first = missing.find(Boolean);
        var message = first ? fieldLabel(first) + ' is required and must contain a valid value.' : 'Please complete all required fields.';
        // Run this step if the show error equals function.
        if (typeof window.showError === 'function') window.showError(message); else window.alert(message);
        // Run this step if the first is set.
        if (first) first.focus();
        return false;
    }

    // Build the list of values to review.
    function reviewRows(modal) {
        var rows = [];
        // Process each control.
        modal.querySelectorAll('.modal-body input, .modal-body select, .modal-body textarea').forEach(function (control) {
            // Return early if the closest check matches or the control disabled is set or the control type equals hidden.
            if (control.closest('.pfims-review-panel') || control.disabled || control.type === 'hidden') return;
            var group = control.closest('.form-group');
            // Return early if the group is set and the get computed style display equals none.
            if (group && getComputedStyle(group).display === 'none') return;
            var value = fieldValue(control);
            // Return early if the value is missing or false or the test check matches.
            if (!value || /^(select|no file chosen)/i.test(value)) return;
            rows.push([fieldLabel(control), value]);
        });
        return rows;
    }

    // Check whether this form is adding a record.
    function isAddMode(modal) {
        return !modal.classList.contains('is-editing') && !modal.getAttribute('data-edit-id');
    }

    // Switch between editing and reviewing the details.
    function setMode(flow, review) {
        var rows = review ? reviewRows(flow.modal) : [];
        flow.mode = review ? 'review' : 'details';
        // Process each element.
        flow.details.forEach(function (element) { element.hidden = review; });
        flow.panel.hidden = !review;
        flow.detailsStep.classList.toggle('active', !review);
        flow.detailsStep.classList.toggle('completed', review);
        flow.reviewStep.classList.toggle('active', review);
        flow.back.hidden = !review;
        flow.save.classList.toggle('btn-continue', !review);
        flow.save.textContent = review ? flow.originalSaveText : 'Continue';
        // Run this step if the review is set.
        if (review) {
            flow.list.replaceChildren();
            // Process each entry.
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

    // Get the install.
    function install(modal) {
        // Return early if the modal is missing or false or the review flow equals ready.
        if (!modal || modal.dataset.reviewFlow === 'ready') return;
        // Run the find action.
        var save = Array.from(modal.querySelectorAll('.modal-footer button')).find(function (button) {
            return /save|add/i.test((button.getAttribute('onclick') || '') + ' ' + (button.textContent || ''))
                && !/delete|cancel/i.test((button.getAttribute('onclick') || '') + ' ' + (button.textContent || ''));
        });
        var body = modal.querySelector('.modal-body');
        var header = modal.querySelector('.modal-header');
        // Return early if the save is missing or false or the body is missing or false or the header is missing or false.
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

        // Handle the click event.
        back.addEventListener('click', function () { setMode(flow, false); });
        // Handle the click event.
        save.addEventListener('click', function (event) {
            // Return early if the is add mode is missing or false.
            if (!isAddMode(modal)) return;
            // Return early if the mode equals review.
            if (flow.mode === 'review') return;
            event.preventDefault();
            event.stopImmediatePropagation();
            // Run this step if the validate check matches.
            if (validate(flow, modal)) setMode(flow, true);
        }, true);
        // Handle the click event.
        modal.addEventListener('click', function (event) {
            // Run this step if the target equals the modal or the closest check matches.
            if (event.target === modal || event.target.closest('.modal-close, .btn-cancel')) {
                // Run this action after the delay.
                window.setTimeout(function () { setMode(flow, false); }, 0);
            }
        });
        // Match flow.
        function syncFlow() {
            var available = isAddMode(modal);
            stepper.hidden = !available;
            back.hidden = !available || flow.mode !== 'review';
            // Run this step if the available is missing or false.
            if (!available) {
                // Process each element.
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

    // Set up this page.
    function initialize() {
        // Process each id.
        Object.keys(flows).forEach(function (id) { install(document.getElementById(id)); });
    }

    // Run this step if the ready state equals loading.
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
})();
