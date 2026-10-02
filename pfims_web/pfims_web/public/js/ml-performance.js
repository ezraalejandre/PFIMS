(() => {
    const root = document.getElementById('modelMetrics');
    if (!root) { window.pfimsPerformance = { update() {} }; return; }
    let active = {}, reports = {}, busy = false;
    const element = id => document.getElementById(id);
    const put = (id, value) => { element(id).textContent = value; };
    const number = value => value !== null && value !== undefined && Number.isFinite(Number(value));
    const percent = value => number(value) ? `${Number(value).toFixed(2)}%` : 'Unavailable';
    const currency = value => number(value) ? `₱${Number(value).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}` : 'Unavailable';
    const names = values => (values || []).map(value => value.replaceAll('_', ' ')).join(', ');
    function render() {
        const source = element('performanceSource').value;
        const candidate = source !== 'active';
        const report = candidate ? reports[source] : null;
        const evaluated = candidate ? report?.evaluation : active;
        const latest = element('performanceWeighting').value === 'latest';
        const latestMetrics = evaluated?.latest_observation_per_project;
        const metrics = latest ? latestMetrics : evaluated;
        element('performanceWeighting').disabled = !latestMetrics || busy;
        const definition = element('performanceThreshold').value;
        const detection = metrics?.overrun_detection?.[definition];
        // Older metadata supports only the material-overrun scores, never infer any-overrun scores.
        const legacy = !candidate && !latest && definition === 'material_overrun' && !active.overrun_detection;
        const scores = detection || (legacy ? active : {});
        put('metricMAPE', percent(metrics?.mean_absolute_percentage_error));
        put('metricMAE', currency(metrics?.mean_absolute_error));
        put('metricRSquared', number(metrics?.r_squared) ? Number(metrics.r_squared).toFixed(4) : 'Unavailable');
        put('metricPrecision', percent(scores.precision));
        put('metricRecall', percent(scores.recall));
        put('metricF1', percent(scores.f1_score));
        put('metricOverrunAccuracy', percent(detection?.classification_accuracy ?? (legacy ? active.overrun_classification_accuracy : null)));
        const observations = metrics?.evaluation_observations ?? (candidate ? null : active.test_samples);
        const projects = metrics?.evaluation_projects ?? (candidate ? report?.holdout_project_ids?.length : null);
        put('samplesCount', `${projects ?? 'Unknown'} test projects · ${observations ?? 'Unknown'} observations`);
        put('performanceScope', candidate
            ? 'Presentation dataset performance. Candidate evaluation only; these scores do not describe the active forecasting model.'
            : 'Saved evaluation of the active forecasting model. Current evaluation results may use a different set of completed projects and cannot be compared directly.');
        const baseline = report?.budget_baseline_comparison;
        const comparison = report?.model_comparison;
        const passes = baseline?.model_outperforms_budget_baseline === true && comparison?.production_model_is_best_option === true;
        put('performanceStatus', candidate
            ? !report ? 'No saved evaluation. Use Refresh evaluation to evaluate current completed projects.'
                : report.status !== 'evaluated' ? 'Not enough eligible projects to evaluate this model.'
                : passes ? 'Candidate meets the comparison requirements. Evaluation does not activate it.'
                : 'Candidate does not meet the activation requirements. Active forecasts are unchanged.'
            : `Active forecast approach: ${active.prediction_strategy === 'progress_snapshot_model' ? 'progress-based remaining cost' : active.prediction_strategy === 'planning_only_baseline' ? 'planning estimate from budget and duration' : 'unavailable'}.`);
        const counts = detection?.classification_counts;
        put('performanceCounts', counts
            ? `${counts.tp} overruns detected · ${counts.fn} overruns missed · ${counts.fp} false alerts · ${counts.tn} within-budget outcomes correctly identified. ${detection.actual_overruns} actual overruns across ${detection.evaluated_observations} observations.`
            : 'Detailed detection counts are unavailable for this saved evaluation. A zero recall means no actual overruns were detected; an unavailable score cannot be treated as zero.');
        const cv = candidate ? report?.cross_validation : active.cross_validation;
        put('performanceValidation', cv ? `Training-only temporal cross-validation: average percentage error ${percent(cv.average_mean_absolute_percentage_error)}. Test results above use reserved projects.` : 'Training cross-validation is unavailable for this saved model.');
        put('performanceComparison', baseline
            ? `Candidate cost error ${percent(evaluated?.mean_absolute_percentage_error)}; budget baseline error ${percent(baseline.baseline_evaluation?.mean_absolute_percentage_error)}. Activation requires at least a 2 percentage-point improvement and no lower-error comparison model. Activation comparison uses all held-out stage observations, regardless of the observation filter above.`
            : 'Baseline comparison is unavailable for this saved evaluation.');
        const features = candidate ? report?.evaluated_feature_names : active.feature_set?.selected_feature_names || active.feature_set?.feature_names;
        put('performanceFeatures', features?.length ? `Inputs evaluated (${features.length}): ${names(features)}.` : active.prediction_strategy === 'planning_only_baseline' && !candidate ? 'Active inputs: recorded budget and planned duration. Progress, burn rate, inventory usage, schedule, and transaction frequency are evaluated in the progress candidate.' : 'Evaluated input details are unavailable.');
        const date = candidate ? report?.generated_at : active.trained_at;
        put('performanceUpdated', date && !Number.isNaN(Date.parse(date)) ? `${candidate ? 'Evaluated' : 'Model trained'}: ${new Date(date).toLocaleString('en-PH')}.` : 'Evaluation date unavailable.');
        const refresh = element('refreshPerformance');
        if (refresh) { refresh.disabled = busy || !candidate; refresh.textContent = busy ? 'Evaluating…' : 'Refresh evaluation'; }
    }
    window.pfimsPerformance = { update(metrics) { active = metrics || {}; reports = active.candidate_evaluations?.reports || {}; render(); } };
    ['performanceSource', 'performanceWeighting', 'performanceThreshold'].forEach(id => element(id).addEventListener('change', () => {
        if (id === 'performanceSource') element('performanceWeighting').value = 'all';
        render();
    }));
    element('refreshPerformance')?.addEventListener('click', async () => {
        if (busy || element('performanceSource').value === 'active') return;
        const cohort = element('performanceSource').value;
        busy = true;
        element('performanceSource').disabled = true;
        render();
        put('performanceStatus', 'Evaluating current completed projects. The active model will remain unchanged.');
        try {
            const response = await fetch(`${document.getElementById('predictiveAnalyticsRoot').dataset.apiBase}/evaluate`, {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({cohort})
            });
            const data = await response.json();
            if (!response.ok || !data.success || !data.report) throw new Error('Evaluation could not be refreshed. Try again shortly; saved results are retained.');
            reports[cohort] = data.report;
            busy = false;
            render();
        } catch (error) {
            busy = false;
            render();
            put('performanceStatus', error.message);
        } finally {
            element('performanceSource').disabled = false;
        }
    });
})();
