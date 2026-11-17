// Set up the ml performance page actions.
(() => {
    const root = document.getElementById('modelMetrics');
    // Run this step if the root is missing or false.
    if (!root) { window.pfimsPerformance = { update() {} }; return; }
    let active = {};
    const element = id => document.getElementById(id);
    // Get the put.
    const put = (id, value) => { const node = element(id); if (node) node.textContent = value; };
    const number = value => value !== null && value !== undefined && Number.isFinite(Number(value));
    const percent = value => number(value) ? `${Number(value).toFixed(2)}%` : 'Unavailable';
    const currency = value => number(value) ? `₱${Number(value).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}` : 'Unavailable';
    let savedDisplay = null;
    try {
        const saved = JSON.parse(element('savedCostPerformanceBootstrap')?.textContent || 'null');
        if (saved?.mode === 'saved_evaluation_display_only' && saved.model_type === 'gradient_boosting_regression'
            && ['mean_absolute_error', 'mean_absolute_percentage_error', 'r_squared'].every(key => number(saved.metrics?.[key]))) savedDisplay = saved;
        if (saved?.mode === 'merged_development_evaluation'
            && ['mean_absolute_error', 'mean_absolute_percentage_error', 'r_squared'].every(key => number(saved.metrics?.[key]))) savedDisplay = saved;
    } catch { /* An unavailable saved comparison falls back to the active evaluation. */ }
    // Get the scope note.
    function scopeNote(titleId, noteId, text) {
        const title = element(titleId);
        // Return early if the title is missing or false or the create element is missing or false.
        if (!title || !document.createElement) return;
        // Run this step if the element is missing or false.
        if (!element(noteId)) {
            const note = document.createElement('p');
            note.id = noteId;
            note.className = 'analytics-panel-description';
            title.insertAdjacentElement('afterend', note);
        }
        put(noteId, text);
    }
    const featureLabels = {
        budget: 'Approved budget', duration_months: 'Planned duration (months)', worker_count: 'Number of workers',
        elapsed_days: 'Days since project start', remaining_planned_days: 'Days until planned completion',
        elapsed_time_fraction: 'Share of planned time elapsed', fin_total_expense: 'Recorded spending',
        budget_used_fraction: 'Share of budget spent', material_cost_share: 'Material share of spending',
        labor_cost_share: 'Labor share of spending', equipment_cost_share: 'Equipment share of spending',
        cost_burn_rate_30d: 'Daily spending over the last 30 days', burn_rate_acceleration: 'Change in spending pace',
        inventory_cost_burn_rate_30d: 'Daily material usage cost over the last 30 days',
        expense_frequency_7d: 'Expense activity over the last 7 days', stock_out_frequency_7d: 'Material withdrawals over the last 7 days',
    };
    const names = values => (values || []).map(value => featureLabels[value] || value.replaceAll('_', ' ')).join(', ');
    // Keep every saved detail, but present individual facts instead of dense paragraphs.
    function facts(id, chips = false) {
        const node = element(id);
        // Return early if the value is missing or false or the create element is missing or false.
        if (!node?.replaceChildren || !document.createElement) return;
        const text = node.textContent.trim();
        // Return early if the text is missing or false.
        if (!text) return;
        const list = document.createElement('ul');
        list.className = chips ? 'performance-chips' : 'performance-facts';
        const values = chips ? text.replace(/^Inputs evaluated \(\d+\): /, '').replace(/\.$/, '').split(', ') : text.split(/(?<=[.!?])\s+(?=[A-Z“0-9])| · /);
        // Process each value.
        values.forEach(value => { const item = document.createElement('li'); item.textContent = value; list.appendChild(item); });
        node.replaceChildren(list);
    }
    // Get the render.
    function render() {
        const metrics = savedDisplay?.metrics || active;
        const merged = savedDisplay?.mode === 'merged_development_evaluation';
        put('performanceStatus', merged ? 'Cost Prediction Performance' : savedDisplay ? 'Saved Gradient Boosting Performance' : 'Cost Prediction Performance');
        put('costPerformanceSource', merged ? savedDisplay.scope : savedDisplay
            ? 'Saved comparison: latest-checkpoint MAE/MAPE and chronological-validation R². Active predictor remains SVR (RBF).'
            : active.model_type === 'support_vector_regression_rbf'
            ? 'SVR (RBF) · all saved historical test checkpoints' : 'Saved active model evaluation · all checkpoints');
        if (merged) {
            const source = element('costPerformanceSource');
            if (source) {
                const label = document.createElement('strong'); label.textContent = 'SVR (RBF)';
                source.replaceChildren(label, document.createTextNode(` · ${savedDisplay.scope}`));
            }
        }
        put('metricMAPE', percent(metrics?.mean_absolute_percentage_error));
        put('metricMAE', currency(metrics?.mean_absolute_error));
        const displayedRSquared = metrics.r_squared;
        put('metricRSquared', number(displayedRSquared) ? Number(displayedRSquared).toFixed(4) : 'Unavailable');
        put('metricRSquaredLabel', 'R²');
        if (merged) window.pfimsExperimentPerformance?.update(savedDisplay);
        if (Object.prototype.hasOwnProperty.call(active, 'pace_experiment')) {
            window.pfimsExperimentPerformance?.update(active.pace_experiment);
        }
    }
    window.pfimsPerformance = { update(metrics) {
        active = metrics || {};
        if (active.development_evaluation?.mode === 'merged_development_evaluation') savedDisplay = active.development_evaluation;
        render();
    } };
})();
