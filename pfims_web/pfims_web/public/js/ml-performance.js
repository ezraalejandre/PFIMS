(() => {
    const root = document.getElementById('modelMetrics');
    if (!root) { window.pfimsPerformance = { update() {} }; return; }
    let active = {};
    const element = id => document.getElementById(id);
    const put = (id, value) => { const node = element(id); if (node) node.textContent = value; };
    const number = value => value !== null && value !== undefined && Number.isFinite(Number(value));
    const percent = value => number(value) ? `${Number(value).toFixed(2)}%` : 'Unavailable';
    const currency = value => number(value) ? `₱${Number(value).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}` : 'Unavailable';
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
        if (!node?.replaceChildren || !document.createElement) return;
        const text = node.textContent.trim();
        if (!text) return;
        const list = document.createElement('ul');
        list.className = chips ? 'performance-chips' : 'performance-facts';
        const values = chips ? text.replace(/^Inputs evaluated \(\d+\): /, '').replace(/\.$/, '').split(', ') : text.split(/(?<=[.!?])\s+(?=[A-Z“0-9])| · /);
        values.forEach(value => { const item = document.createElement('li'); item.textContent = value; list.appendChild(item); });
        node.replaceChildren(list);
    }
    function render() {
        const candidate = false;
        const report = null;
        const evaluated = active;
        const metrics = active;
        const projectDetection = metrics.latest_observation_per_project?.overrun_detection?.any_overrun;
        const useProjectDetection = metrics.overrun_detector_source !== 'independent_classifier' && !!projectDetection;
        const detectionUsesProjects = useProjectDetection || metrics.overrun_detector_evaluation_unit === 'latest_observation_per_project';
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
        const maeTarget = metrics.mae_target;
        put('performanceMAETarget', number(maeTarget?.reference_pesos) && Number(maeTarget.reference_pesos) > 0
            ? `MAE is ${percent(maeTarget.actual_percent)} of the median actual final cost (${currency(maeTarget.reference_pesos)}). Goal: at most ${currency(maeTarget.maximum_pesos)} (15%); preferred target: ${currency(maeTarget.stretch_pesos)} (10%).`
            : 'MAE goal: at most 15% of the median actual final cost, with 10% as the preferred target. The median cost was not saved for this active evaluation, so its peso equivalent is unavailable.');
        put('metricRSquared', number(metrics?.r_squared) ? Number(metrics.r_squared).toFixed(4) : 'Unavailable');
        put('metricPrecision', percent(scores.precision));
        put('metricRecall', percent(scores.recall));
        put('metricF1', percent(scores.f1_score));
        put('metricOverrunAccuracy', percent(detection?.classification_accuracy));
        const observations = metrics?.evaluation_observations ?? (candidate ? null : active.test_samples);
        const projects = metrics?.evaluation_projects ?? (candidate ? report?.holdout_project_ids?.length : null);
        put('samplesCount', `${projects ?? 'Unknown'} test projects · ${observations ?? 'Unknown'} observations`);
        const augmentation = active.augmentation;
        put('performanceScope', augmentation
            ? 'Database-only test evaluation. Ingested projects are used for training only; none are included in these test scores.'
            : active.evaluation_scope_label === 'Demonstration evaluation'
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
            : `Active forecast approach: ${active.prediction_strategy === 'planning_spending_model' ? 'planning and spending-based remaining cost' : active.prediction_strategy === 'progress_snapshot_model' ? 'progress-based remaining cost' : active.prediction_strategy === 'planning_only_baseline' ? 'planning estimate from budget and duration' : 'unavailable'}.`);
        const counts = detection?.classification_counts;
        put('performanceCounts', counts
            ? `${counts.tp} overruns detected · ${counts.fn} overruns missed · ${counts.fp} false alerts · ${counts.tn} within-budget outcomes correctly identified. ${detection.actual_overruns} actual overruns across ${detection.evaluated_observations} ${detectionUsesProjects ? 'test projects, using the latest observation from each project' : 'observations'}.${useProjectDetection ? ` Across all progress stages, accuracy was ${percent(metrics.overrun_detection?.any_overrun?.classification_accuracy)}.` : ''}`
            : 'Detailed detection counts are unavailable for this saved evaluation. A zero recall means no actual overruns were detected; an unavailable score cannot be treated as zero.');
        const cv = candidate ? report?.cross_validation : active.cross_validation;
        put('performanceValidation', cv ? `${active.training_projects ?? 'Unknown'} training projects · ${projects ?? 'Unknown'} reserved test projects. Training-only temporal cross-validation: average percentage error ${percent(cv.average_mean_absolute_percentage_error)}. Cost errors use ${observations ?? 'Unknown'} stage observations; overrun detection uses ${useProjectDetection ? 'one latest observation per test project' : 'the stated evaluation observations'}.` : 'Training cross-validation is unavailable for this saved model.');
        if (augmentation) {
            const databaseTraining = augmentation.database_training_project_ids?.length;
            const used = number(active.training_projects) && number(databaseTraining) ? Number(active.training_projects) - databaseTraining : augmentation.dummy_projects;
            put('performanceValidation', `${databaseTraining ?? 'Unknown'} database projects + ${used ?? 'Unknown'} ingested projects used for training (${augmentation.dummy_projects ?? 'Unknown'} available) · ${augmentation.database_test_project_ids?.length ?? 'Unknown'} database projects reserved for testing. Database split: earlier ${Math.round((1 - (augmentation.database_test_ratio ?? .20)) * 100)}% for training, newest ${Math.round((augmentation.database_test_ratio ?? .20) * 100)}% for testing. Training-only temporal cross-validation: average percentage error ${percent(cv?.average_mean_absolute_percentage_error)}. Each validation fold excludes ingested projects derived from its validation projects.`);
        }
        put('performanceComparison', baseline
            ? `Active model cost error ${percent(baseline.model_evaluation?.mean_absolute_percentage_error ?? evaluated?.mean_absolute_percentage_error)}; recorded-budget baseline error ${percent(baseline.baseline_evaluation?.mean_absolute_percentage_error)} on the same test observations${active.prediction_strategy === 'planning_spending_model' && baseline.model_evaluation ? ', using one latest checkpoint per test project' : ''}.`
            : 'Baseline comparison is unavailable for this saved evaluation.');
        const features = candidate ? report?.evaluated_feature_names : active.feature_set?.selected_feature_names || active.feature_set?.feature_names;
        put('performanceFeatures', features?.length ? `Inputs evaluated (${features.length}): ${names(features)}.` : active.prediction_strategy === 'planning_only_baseline' && !candidate ? 'Active inputs: recorded budget and planned duration. Progress, burn rate, inventory usage, schedule, and transaction frequency are evaluated in the progress candidate.' : 'Evaluated input details are unavailable.');
        const date = candidate ? report?.generated_at : active.trained_at;
        put('performanceUpdated', date && !Number.isNaN(Date.parse(date)) ? `${candidate ? 'Evaluated' : 'Model trained'}: ${new Date(date).toLocaleString('en-PH')}.` : 'Evaluation date unavailable.');
        const improvement = active.latest_optimization;
        if (!improvement) {
            put('performanceCandidateStatus', 'No optimization evaluation available. The metrics above describe the active model.');
            put('performanceCandidateResults', '');
            put('performanceCandidateTraining', '');
        } else {
            const cost = improvement.evaluation || {};
            const detector = improvement.detector_evaluation || {};
            const failures = [...(improvement.cost_failed_requirements || []), ...(improvement.detector_failed_requirements || [])];
            const reasons = [];
            if (failures.includes('agreed_mae_tolerance')) reasons.push('cost error does not meet the 15%-of-median MAE requirement');
            if (failures.includes('recall_minimum') || failures.includes('f1_score_minimum')) reasons.push('overrun detection is below the activation requirements');
            if (failures.includes('fresh_independent_holdout')) reasons.push('a fresh independent real-project test is still required');
            if (failures.includes('same_holdout_active_comparison')) reasons.push('a reliable comparison with the active model on the same observations is unavailable');
            if (reasons.length === 0 && failures.length) reasons.push('other activation evidence or performance requirements were not met');
            put('performanceCandidateStatus', improvement.active_model_changed
                ? `Latest evaluated model is now active: ${algorithmNames[improvement.algorithm] || 'Unknown model'}.`
                : `Latest candidate: ${algorithmNames[improvement.algorithm] || 'Unknown model'}. Not activated${reasons.length ? `: ${reasons.join('; ')}` : '; evaluation alone does not activate a model'}.`);
            put('performanceCandidateResults', `Candidate cost evaluation: MAE ${currency(cost.mean_absolute_error)}, MAPE ${percent(cost.mean_absolute_percentage_error)}, R² ${number(cost.r_squared) ? Number(cost.r_squared).toFixed(4) : 'Unavailable'}. Separate candidate overrun detector: accuracy ${percent(detector.classification_accuracy)}, precision ${percent(detector.precision)}, recall ${percent(detector.recall)}, F1 ${percent(detector.f1_score)}. ${number(detector.actual_overruns) ? `${detector.actual_overruns} actual test overruns.` : ''}`);
            if (number(cost.mae_target?.reference_pesos) && Number(cost.mae_target.reference_pesos) > 0) {
                put('performanceCandidateResults', element('performanceCandidateResults').textContent + ` Candidate MAE is ${percent(cost.mae_target.actual_percent)} of its median actual final cost (${currency(cost.mae_target.reference_pesos)}); 15% ceiling ${currency(cost.mae_target.maximum_pesos)}, 10% preferred target ${currency(cost.mae_target.stretch_pesos)}.`);
            }
            const primary = cost.latest_observation_per_project;
            if (primary) {
                put('performanceCandidateResults', element('performanceCandidateResults').textContent + ` Using one latest checkpoint per project (the activation check): MAE ${currency(primary.mean_absolute_error)}, MAPE ${percent(primary.mean_absolute_percentage_error)}, R² ${number(primary.r_squared) ? Number(primary.r_squared).toFixed(4) : 'Unavailable'}; MAE is ${percent(primary.mae_target?.actual_percent)} of the same median cost.`);
            }
            const stages = cost.monitoring_segments?.by_elapsed_stage;
            if (stages) {
                put('performanceCandidateResults', element('performanceCandidateResults').textContent + ` MAE by planned time checkpoint: ${['early', 'middle', 'late'].filter(stage => stages[stage]).map(stage => `${stage} ${currency(stages[stage].mean_absolute_error)}`).join(', ')}.`);
            }
            const split = improvement.training_counts || {};
            const updated = improvement.generated_at && !Number.isNaN(Date.parse(improvement.generated_at)) ? new Date(improvement.generated_at).toLocaleString('en-PH') : 'Unavailable';
            const eventCounts = number(split.ingested_expenses_per_project) && number(split.ingested_inventory_transactions_per_project) ? ` Each ingested project has ${split.ingested_expenses_per_project} expenses and ${split.ingested_inventory_transactions_per_project} inventory transactions.` : '';
            const generation = split.ingested_data_method === 'rule_based_construction_ledgers_v1' ? ` Ingested data uses rule-based construction ledgers spanning ${split.ingested_data_year_range?.join('–') ?? '2019–present'}, with projects lasting at most seven months.${eventCounts}` : '';
            put('performanceCandidateTraining', `Candidate training: ${split.database_projects ?? 'Unknown'} database projects + ${split.dummy_projects_used ?? 'Unknown'} ingested projects used (${split.dummy_projects_available ?? 'Unknown'} available). Testing: ${split.test_database_projects ?? 'Unknown'} database projects only.${number(split.database_test_ratio) ? ` Database split: earlier ${Math.round((1 - split.database_test_ratio) * 100)}% for training, newest ${Math.round(split.database_test_ratio * 100)}% for testing.` : ''}${generation} Settings selected using training-only temporal validation. The same saved test projects are reused for model comparisons. Evaluated: ${updated}.`);
            if (improvement.prediction_strategy === 'planning_spending_model') {
                const audit = improvement.data_audit || {};
                const excluded = Object.keys(audit.excluded_projects || {}).length;
                put('performanceCandidateTraining', element('performanceCandidateTraining').textContent + ` Candidate approach: planning and spending, using all ${improvement.feature_names?.length ?? 'Unknown'} shared inputs for linear regression and SVR. Historical costs use expense and withdrawal dates. ${audit.observations ?? 'Unknown'} observations from ${audit.eligible_projects ?? 'Unknown'} eligible completed projects; ${excluded} projects excluded by data checks. ${audit.budget_fallback_observations ?? 'Unknown'} observations use the latest recorded budget because effective budget history is unavailable.`);
            }
        }
        ['performanceScope', 'performanceMAETarget', 'performanceCounts', 'performanceValidation', 'performanceComparison', 'performanceCandidateStatus', 'performanceCandidateResults', 'performanceCandidateTraining'].forEach(id => facts(id));
        facts('performanceFeatures', !!features?.length);
    }
    window.pfimsPerformance = { update(metrics) { active = metrics || {}; render(); } };
})();
