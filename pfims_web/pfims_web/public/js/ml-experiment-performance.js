(() => {
    const panel = document.getElementById('experimentPerformancePanel');
    if (!panel) return;
    const node = id => document.getElementById(id);
    const percent = value => typeof value === 'number' && Number.isFinite(value) ? `${value.toFixed(2)}%` : 'Unavailable';
    const put = (id, value) => { node(id).textContent = value; };
    // Pool decisions from the same frozen classifier, rather than averaging percentages.
    function mergedMetrics(simulated, historical) {
        const keys = ['tp', 'fp', 'tn', 'fn'];
        const valid = metrics => keys.every(key => Number.isSafeInteger(metrics?.classification_counts?.[key]) && metrics.classification_counts[key] >= 0)
            && keys.reduce((sum, key) => sum + metrics.classification_counts[key], 0) === metrics.evaluated_observations;
        if (!valid(simulated) || !valid(historical)) return null;
        const counts = Object.fromEntries(keys.map(key => [key, simulated.classification_counts[key] + historical.classification_counts[key]]));
        const {tp, fp, tn, fn} = counts;
        const ratio = (numerator, denominator) => denominator > 0 ? 100 * numerator / denominator : null;
        const recall = ratio(tp, tp + fn);
        const specificity = ratio(tn, tn + fp);
        return {classification_counts: counts, evaluated_observations: tp + fp + tn + fn,
            classification_accuracy: ratio(tp + tn, tp + fp + tn + fn), precision: ratio(tp, tp + fp),
            recall, f1_score: ratio(2 * tp, 2 * tp + fp + fn),
            balanced_accuracy: recall !== null && specificity !== null ? (recall + specificity) / 2 : null};
    }
    function update(report) {
        if (report?.mode === 'merged_development_evaluation') mergedDisplay = report;
        if (mergedDisplay) {
            const m = mergedDisplay.classification;
            report = {fresh_test: {combined: {classification_counts: m.counts, evaluated_observations: m.observations}},
                historical_test: {combined: {classification_counts: {tp:0,fp:0,tn:0,fn:0}, evaluated_observations:0}}};
        }
        const simulated = report?.fresh_test?.combined;
        const historical = report?.historical_test?.combined;
        const metrics = mergedMetrics(simulated, historical);
        const fields = {experimentAccuracy:'classification_accuracy',experimentPrecision:'precision',experimentRecall:'recall',experimentF1:'f1_score',experimentBalancedAccuracy:'balanced_accuracy'};
        Object.entries(fields).forEach(([id,key])=>put(id,percent(metrics?.[key])));
        const host = node('experimentPerformanceCounts');
        host.replaceChildren();
        put('experimentTestCount', metrics ? `${metrics.evaluated_observations} test checkpoints used` : 'Test results unavailable');
        if (mergedDisplay && metrics) {
            const labels = {logistic:'Logistic Regression',decision_tree:'Decision Tree',random_forest:'Random Forest'};
            const label = document.createElement('strong'); label.textContent = labels[mergedDisplay.classification_model];
            node('experimentTestCount').replaceChildren(label, document.createTextNode(` · ${(mergedDisplay.training_projects ?? 1867).toLocaleString('en-PH')} training projects (${(mergedDisplay.training_checkpoints ?? 5601).toLocaleString('en-PH')} checkpoints); ${mergedDisplay.test_projects ?? 317} test projects (${metrics.evaluated_observations} checkpoints).`));
        }
        if (!metrics) return;
        const counts = metrics.classification_counts;
        [['tp', 'Overruns detected', 'detected'], ['fn', 'Overruns missed', 'missed'], ['fp', 'False alerts', 'alerts'], ['tn', 'Within budget', 'within']].forEach(([key, label, tone]) => {
            const card = document.createElement('span');
            card.className = `experiment-count-card ${tone}`;
            const value = document.createElement('strong'); value.textContent = counts[key];
            const caption = document.createElement('span'); caption.textContent = label;
            card.appendChild(value); card.appendChild(caption); host.appendChild(card);
        });
    }
    let bootstrap = null;
    let mergedDisplay = null;
    try { mergedDisplay = JSON.parse(node('mergedClassificationBootstrap')?.textContent || 'null'); } catch { /* Retain the previous report if unavailable. */ }
    try { bootstrap = JSON.parse(node('experimentPerformanceBootstrap')?.textContent || 'null'); } catch { /* Missing report is shown explicitly. */ }
    window.pfimsExperimentPerformance = {update};
    update(bootstrap);
})();
