(() => {
    const root = document.getElementById('modelMetrics');
    if (!root) { window.pfimsPerformance = { update() {} }; return; }
    let active = {};
    const element = id => document.getElementById(id);
    const put = (id, value) => { element(id).textContent = value; };
    const number = value => value !== null && value !== undefined && Number.isFinite(Number(value));
    const percent = value => number(value) ? `${Number(value).toFixed(2)}%` : 'Unavailable';
    const currency = value => number(value) ? `₱${Number(value).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}` : 'Unavailable';
    const names = values => (values || []).map(value => value.replaceAll('_', ' ')).join(', ');
    function render() {
        const candidate = false;
        const report = null;
        const evaluated = active;
        const metrics = active;
        const projectDetection = metrics.latest_observation_per_project?.overrun_detection?.any_overrun;
        const useProjectDetection = metrics.overrun_detector_source !== 'independent_classifier' && !!projectDetection;
        const detection = useProjectDetection ? projectDetection : metrics.overrun_detection?.any_overrun;
        const scores = detection || {};
        const algorithmNames = {
            least_squares_linear_regression: 'Linear regression',
            ridge_linear_regression: 'Regularized linear regression',
            support_vector_regression_rbf: 'Support vector regression (SVR)',
        };
        const algorithm = algorithmNames[active.model_type];
        put('performanceAlgorithm', algorithm
            ? `Active cost model: ${algorithm}.${active.model_recovery_source === 'previous' ? ' The previous verified model is being used for recovery.' : ''}`
            : 'Active cost model identity is unavailable.');
        put('metricMAPE', percent(metrics?.mean_absolute_percentage_error));
        put('metricMAE', currency(metrics?.mean_absolute_error));
        put('metricRSquared', number(metrics?.r_squared) ? Number(metrics.r_squared).toFixed(4) : 'Unavailable');
        put('metricPrecision', percent(scores.precision));
        put('metricRecall', percent(scores.recall));
        put('metricF1', percent(scores.f1_score));
        put('metricOverrunAccuracy', percent(detection?.classification_accuracy));
        const observations = metrics?.evaluation_observations ?? (candidate ? null : active.test_samples);
        const projects = metrics?.evaluation_projects ?? (candidate ? report?.holdout_project_ids?.length : null);
        put('samplesCount', `${projects ?? 'Unknown'} test projects · ${observations ?? 'Unknown'} observations`);
        put('performanceScope', active.evaluation_scope_label === 'Demonstration evaluation'
            ? 'Demonstration evaluation. These results describe the presentation portfolio and do not establish company operational accuracy.'
            : candidate
            ? 'Presentation dataset performance. Candidate evaluation only; these scores do not describe the active forecasting model.'
            : 'Saved evaluation of the active forecasting model. Current evaluation results may use a different set of completed projects and cannot be compared directly.');
        const baseline = active.budget_baseline_comparison;
        const comparison = active.model_comparison;
        const passes = baseline?.model_outperforms_budget_baseline === true && comparison?.production_model_is_best_option === true;
        put('performanceStatus', candidate
            ? !report ? 'No saved evaluation. Use Refresh evaluation to evaluate current completed projects.'
                : report.status !== 'evaluated' ? 'Not enough eligible projects to evaluate this model.'
                : passes ? 'Candidate meets the comparison requirements. Evaluation does not activate it.'
                : 'Candidate does not meet the activation requirements. Active forecasts are unchanged.'
            : `Active forecast approach: ${active.prediction_strategy === 'progress_snapshot_model' ? 'progress-based remaining cost' : active.prediction_strategy === 'planning_only_baseline' ? 'planning estimate from budget and duration' : 'unavailable'}.`);
        const counts = detection?.classification_counts;
        put('performanceCounts', counts
            ? `${counts.tp} overruns detected · ${counts.fn} overruns missed · ${counts.fp} false alerts · ${counts.tn} within-budget outcomes correctly identified. ${detection.actual_overruns} actual overruns across ${detection.evaluated_observations} ${useProjectDetection ? 'test projects, using the latest observation from each project' : 'observations'}.${useProjectDetection ? ` Across all progress stages, accuracy was ${percent(metrics.overrun_detection?.any_overrun?.classification_accuracy)}.` : ''}`
            : 'Detailed detection counts are unavailable for this saved evaluation. A zero recall means no actual overruns were detected; an unavailable score cannot be treated as zero.');
        const cv = candidate ? report?.cross_validation : active.cross_validation;
        put('performanceValidation', cv ? `${active.training_projects ?? 'Unknown'} training projects · ${projects ?? 'Unknown'} reserved test projects. Training-only temporal cross-validation: average percentage error ${percent(cv.average_mean_absolute_percentage_error)}. Cost errors use ${observations ?? 'Unknown'} stage observations; overrun detection uses ${useProjectDetection ? 'one latest observation per test project' : 'the stated evaluation observations'}.` : 'Training cross-validation is unavailable for this saved model.');
        put('performanceComparison', baseline
            ? `Active model cost error ${percent(evaluated?.mean_absolute_percentage_error)}; recorded-budget baseline error ${percent(baseline.baseline_evaluation?.mean_absolute_percentage_error)} on the same test observations.`
            : 'Baseline comparison is unavailable for this saved evaluation.');
        const features = candidate ? report?.evaluated_feature_names : active.feature_set?.selected_feature_names || active.feature_set?.feature_names;
        put('performanceFeatures', features?.length ? `Inputs evaluated (${features.length}): ${names(features)}.` : active.prediction_strategy === 'planning_only_baseline' && !candidate ? 'Active inputs: recorded budget and planned duration. Progress, burn rate, inventory usage, schedule, and transaction frequency are evaluated in the progress candidate.' : 'Evaluated input details are unavailable.');
        const date = candidate ? report?.generated_at : active.trained_at;
        put('performanceUpdated', date && !Number.isNaN(Date.parse(date)) ? `${candidate ? 'Evaluated' : 'Model trained'}: ${new Date(date).toLocaleString('en-PH')}.` : 'Evaluation date unavailable.');
    }
    window.pfimsPerformance = { update(metrics) { active = metrics || {}; render(); } };
})();
