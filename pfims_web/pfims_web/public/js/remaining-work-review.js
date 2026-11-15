(() => {
    const root = document.getElementById('remainingWorkReview');
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const select = document.getElementById('predictionProject');
    const form = document.getElementById('remainingWorkForm');
    const items = document.getElementById('remainingWorkItems');
    const status = document.getElementById('remainingWorkStatus');
    const save = document.getElementById('saveRemainingWorkReview');
    const notes = document.getElementById('remainingWorkNotes');
    const confirm = document.getElementById('remainingWorkConfirm');
    let requestVersion = 0;
    const currency = amount => new Intl.NumberFormat('en-PH', {style:'currency',currency:'PHP'}).format(amount);
    function addItem(data = {}) {
        const fragment = document.getElementById('remainingWorkItemTemplate').content.cloneNode(true);
        fragment.querySelectorAll('[data-field]').forEach(input => { if (data[input.dataset.field] !== undefined) input.value = data[input.dataset.field]; });
        const fieldset = fragment.querySelector('fieldset');
        const kind = fragment.querySelector('[data-field=kind]');
        const reference = fragment.querySelector('[data-field=reference]');
        const updateReference = () => { reference.required = kind.value === 'committed'; };
        kind.addEventListener('change', updateReference); updateReference();
        fragment.querySelector('button').addEventListener('click', () => { fieldset.remove(); if (!items.children.length) addItem(); });
        items.appendChild(fragment);
    }
    function display(review) {
        items.replaceChildren(); confirm.checked = false; notes.value = review?.notes || '';
        (review?.items?.length ? review.items : [{}]).forEach(addItem);
        status.textContent = !review ? 'No saved review. Enter unpaid commitments and other remaining work.'
            : review.needs_refresh ? `Review from ${review.recorded_at} needs refreshing because its costs, budget, or age changed. Save an updated review before using its total.`
            : `Reviewed final-cost estimate: ${currency(review.review_final_cost)} = recorded spending ${currency(review.recorded_spend)} + unpaid commitments ${currency(review.committed_remaining)} + other remaining work ${currency(review.uncommitted_remaining)}. Saved ${review.recorded_at}. This management estimate is separate from the ML forecast.`;
    }
    async function refresh() {
        const version = ++requestVersion;
        save.disabled = true;
        if (!select.value) { display(null); status.textContent = 'Select a project to review its remaining costs.'; return; }
        status.textContent = 'Loading the latest review…';
        try {
            const response = await fetch(`${root.dataset.api}/${encodeURIComponent(select.value)}`, {headers:{Accept:'application/json'},credentials:'same-origin'});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'The review could not be loaded.');
            if (version !== requestVersion) return;
            display(data.review); save.disabled = false;
        } catch (error) { if (version === requestVersion) status.textContent = error.message; }
    }
    document.getElementById('addRemainingWorkItem').addEventListener('click', () => addItem());
    select.addEventListener('change', refresh);
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!select.value || !form.reportValidity()) return;
        const projectId = select.value;
        const payload = {notes:notes.value,excludes_recorded_costs:confirm.checked,items:[...items.children].map(row => Object.fromEntries([...row.querySelectorAll('[data-field]')].map(input => [input.dataset.field,input.value])))};
        save.disabled = true;
        try {
            const response = await fetch(`${root.dataset.api}/${encodeURIComponent(projectId)}`, {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify(payload)});
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'The review could not be saved.');
            if (projectId === select.value) display(data.review);
        } catch (error) { if (projectId === select.value) status.textContent = error.message; }
        finally { save.disabled = !select.value; }
    });
    window.pfimsRemainingWork = {refresh};
    addItem();
})();
