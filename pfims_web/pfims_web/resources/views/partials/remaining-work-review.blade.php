<link rel="stylesheet" href="{{ asset('css/remaining-work-review.css') }}?v={{ filemtime(public_path('css/remaining-work-review.css')) }}">
<details id="remainingWorkReview" class="remaining-work-review" data-api="{{ url('/api/ml/remaining-work') }}">
    <summary>Review commitments and remaining work</summary>
    <p>Record only unpaid commitments and work still to be done. Include approved variations once in the appropriate item. Quantities and prices are estimates reviewed today; previous reviews remain in history.</p>
    <p id="remainingWorkStatus" role="status" aria-live="polite">Select a project to review its remaining costs.</p>
    <form id="remainingWorkForm">
        <div id="remainingWorkItems"></div>
        <button type="button" id="addRemainingWorkItem" class="btn btn-outline">Add work item</button>
        <label>Review notes<textarea id="remainingWorkNotes" maxlength="1000" rows="2"></textarea></label>
        <label class="remaining-work-confirm"><input type="checkbox" id="remainingWorkConfirm" required>These items exclude costs already recorded and do not duplicate one another.</label>
        <button type="submit" id="saveRemainingWorkReview" class="btn btn-primary" disabled>Save today's review</button>
    </form>
    <template id="remainingWorkItemTemplate">
        <fieldset class="remaining-work-item">
            <legend>Remaining work item</legend>
            <label>Description<input data-field="description" required maxlength="200"></label>
            <label>Cost type<select data-field="kind"><option value="uncommitted">Not yet committed</option><option value="committed">Unpaid commitment</option></select></label>
            <label>Resource<select data-field="resource"><option value="material">Material</option><option value="labor">Labor</option><option value="equipment">Equipment</option><option value="subcontract">Subcontract</option><option value="other">Other</option></select></label>
            <label>Remaining quantity<input data-field="remaining_quantity" type="number" min="0.0001" step="any" max="10000000" value="1" required></label>
            <label>Cost per unit (₱)<input data-field="unit_cost" type="number" min="0.01" step="0.01" max="999999999" required></label>
            <label>Purchase order / contract reference<input data-field="reference" maxlength="100"></label>
            <label>Approved variation reference (if applicable)<input data-field="variation_reference" maxlength="100"></label>
            <button type="button" class="btn btn-outline remove-remaining-work-item">Remove item</button>
        </fieldset>
    </template>
</details>
<script src="{{ asset('js/remaining-work-review.js') }}?v={{ filemtime(public_path('js/remaining-work-review.js')) }}"></script>
